<?php

use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketDepartment;
use App\Models\TicketEvent;
use App\Models\TicketTag;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->tenant = Tenant::factory()->create();
    $this->brand = Brand::factory()->create(['tenant_id' => $this->tenant->id]);

    app()->instance('currentTenant', $this->tenant);
    app()->instance('currentBrand', $this->brand);

    $this->admin = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'brand_id' => $this->brand->id,
    ]);
    $this->admin->assignRole('Admin');

    $this->agent = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'brand_id' => $this->brand->id,
    ]);
    $this->agent->assignRole('Agent');

    $this->viewer = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'brand_id' => $this->brand->id,
    ]);
    $this->viewer->assignRole('Viewer');

    $this->department = TicketDepartment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'brand_id' => $this->brand->id,
    ]);

    $this->categories = TicketCategory::factory()->count(2)->create([
        'tenant_id' => $this->tenant->id,
        'brand_id' => $this->brand->id,
    ]);

    $this->tags = TicketTag::factory()->count(3)->create([
        'tenant_id' => $this->tenant->id,
        'brand_id' => $this->brand->id,
    ]);

    $this->headers = [
        'X-Tenant' => $this->tenant->slug,
        'X-Brand' => $this->brand->slug,
        'X-Correlation-ID' => 'test-correlation-'.Str::uuid(),
    ];
});

it('E1-F3-I2 allows admins to create tickets with scoped taxonomy', function (): void {
    actingAs($this->admin);

    $payload = [
        'subject' => 'Data feed issue '.Str::uuid(),
        'status' => 'open',
        'priority' => 'high',
        'department_id' => $this->department->id,
        'category_ids' => $this->categories->pluck('id')->all(),
        'tag_ids' => $this->tags->take(2)->pluck('id')->all(),
        'metadata' => ['channel' => 'api'],
    ];

    $response = $this->withHeaders($this->headers)->postJson('/api/v1/tickets', $payload);

    $response->assertCreated()
        ->assertJsonPath('data.department.id', $this->department->id)
        ->assertJsonPath('data.categories.0.id', $this->categories->first()->id)
        ->assertJsonPath('data.tags.0.id', $this->tags->first()->id)
        ->assertJsonPath('data.metadata.channel', 'api');

    $ticket = Ticket::with(['categories', 'tags', 'departmentRelation'])->first();

    expect($ticket)->not->toBeNull()
        ->and($ticket->categories)->toHaveCount(2)
        ->and($ticket->tags)->toHaveCount(2)
        ->and($ticket->department_id)->toBe($this->department->id)
        ->and(AuditLog::where('action', 'ticket.created')->where('auditable_id', $ticket->id)->exists())->toBeTrue()
        ->and(TicketEvent::where('ticket_id', $ticket->id)->where('type', 'ticket.created')->exists())->toBeTrue();

    expect(TicketEvent::where('ticket_id', $ticket->id)->where('type', 'ticket.created')->first()?->correlation_id)->not->toBeEmpty();
});

it('E1-F3-I2 allows agents to create tickets with taxonomy', function (): void {
    actingAs($this->agent);

    $payload = [
        'subject' => 'Agent created ticket',
        'status' => 'open',
        'priority' => 'medium',
        'department_id' => $this->department->id,
        'category_ids' => [$this->categories->first()->id],
        'tag_ids' => [$this->tags->first()->id],
    ];

    $this->withHeaders($this->headers)->postJson('/api/v1/tickets', $payload)->assertCreated();

    expect(Ticket::count())->toBe(1);
});

it('E1-F3-I2 blocks viewers from creating tickets', function (): void {
    actingAs($this->viewer);

    $response = $this->withHeaders($this->headers)->postJson('/api/v1/tickets', [
        'subject' => 'Viewer attempt',
        'status' => 'open',
        'priority' => 'low',
    ]);

    $response->assertForbidden()->assertJsonPath('error.code', 'ERR_HTTP_403');
});

it('E1-F3-I2 rejects taxonomy outside the tenant scope', function (): void {
    $otherTenant = Tenant::factory()->create();
    $otherBrand = Brand::factory()->create(['tenant_id' => $otherTenant->id]);
    $foreignCategory = TicketCategory::factory()->create([
        'tenant_id' => $otherTenant->id,
        'brand_id' => $otherBrand->id,
    ]);

    actingAs($this->admin);

    $response = $this->withHeaders($this->headers)->postJson('/api/v1/tickets', [
        'subject' => 'Bad taxonomy',
        'status' => 'open',
        'priority' => 'high',
        'category_ids' => [$foreignCategory->id],
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'ERR_VALIDATION');
});

it('E1-F3-I2 enforces tenant isolation on ticket retrieval', function (): void {
    $otherTenant = Tenant::factory()->create();
    $otherBrand = Brand::factory()->create(['tenant_id' => $otherTenant->id]);
    $otherTicket = Ticket::factory()->create([
        'tenant_id' => $otherTenant->id,
        'brand_id' => $otherBrand->id,
    ]);

    actingAs($this->admin);

    $this->withHeaders($this->headers)->getJson("/api/v1/tickets/{$otherTicket->id}")
        ->assertNotFound()
        ->assertJsonPath('error.code', 'ERR_HTTP_404');
});

it('E1-F3-I2 updates taxonomy and records audit entries', function (): void {
    actingAs($this->admin);

    $ticket = Ticket::factory()->create([
        'tenant_id' => $this->tenant->id,
        'brand_id' => $this->brand->id,
        'department_id' => $this->department->id,
    ]);
    $ticket->categories()->sync([$this->categories->first()->id]);
    $ticket->tags()->sync([$this->tags->first()->id]);

    $payload = [
        'priority' => 'high',
        'department_id' => $this->department->id,
        'category_ids' => $this->categories->pluck('id')->all(),
        'tag_ids' => $this->tags->pluck('id')->all(),
    ];

    $response = $this->withHeaders($this->headers)->patchJson("/api/v1/tickets/{$ticket->id}", $payload);

    $response->assertOk()
        ->assertJsonPath('data.priority', 'high')
        ->assertJsonCount(2, 'data.categories')
        ->assertJsonCount(3, 'data.tags');

    $ticket->refresh();

    expect($ticket->priority)->toBe('high')
        ->and($ticket->tags)->toHaveCount(3)
        ->and($ticket->categories)->toHaveCount(2)
        ->and(AuditLog::where('action', 'ticket.updated')->where('auditable_id', $ticket->id)->exists())->toBeTrue()
        ->and(TicketEvent::where('ticket_id', $ticket->id)->where('type', 'ticket.updated')->exists())->toBeTrue();

    expect(TicketEvent::where('ticket_id', $ticket->id)->where('type', 'ticket.updated')->first()?->correlation_id)->not->toBeEmpty();
});
