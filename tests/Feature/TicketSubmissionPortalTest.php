<?php

use App\Models\Brand;
use App\Models\Message;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketSubmission;
use App\Models\User;
use App\Notifications\TicketPortalSubmissionConfirmation;
use App\Services\TenantRoleProvisioner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Notifications\AnonymousNotifiable;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;

beforeEach(function (): void {
    Notification::fake();
    Storage::fake('local');

    if (app()->bound('currentTenant')) {
        app()->forgetInstance('currentTenant');
    }

    if (app()->bound('currentBrand')) {
        app()->forgetInstance('currentBrand');
    }
});

function portalHeaders(Tenant $tenant, ?Brand $brand = null): array
{
    $headers = [
        'X-Tenant' => $tenant->slug,
        'Accept' => 'application/json',
    ];

    if ($brand) {
        $headers['X-Brand'] = $brand->slug;
    }

    return $headers;
}

it('E1-F1-I4 accepts portal API submissions and creates linked records', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    $payload = [
        'name' => 'Portal User',
        'email' => 'portal.user@example.com',
        'subject' => 'Portal Ticket',
        'message' => 'I need help resetting my access credentials.',
        'tags' => ['support', 'access'],
    ];

    $response = postJson('/api/v1/portal/tickets', $payload, portalHeaders($tenant, $brand));

    $response->assertCreated();
    $response->assertJsonPath('data.status', TicketSubmission::STATUS_ACCEPTED);

    $submission = TicketSubmission::query()->with(['ticket', 'contact', 'messageRecord'])->firstOrFail();

    expect($submission->channel)->toBe(TicketSubmission::CHANNEL_PORTAL);
    expect($submission->ticket)->not->toBeNull();
    expect($submission->ticket->channel)->toBe(Ticket::CHANNEL_PORTAL);
    expect($submission->contact->email)->toBe('portal.user@example.com');
    expect($submission->metadata['ip_hash'] ?? null)->toBe(hash('sha256', '127.0.0.1'));
    expect($submission->tags)->toMatchArray(['support', 'access']);

    $message = Message::query()->where('ticket_id', $submission->ticket_id)->first();
    expect($message)->not->toBeNull();
    expect($message->author_role)->toBe(Message::ROLE_CONTACT);
    expect($message->visibility)->toBe(Message::VISIBILITY_PUBLIC);

    Notification::assertSentOnDemand(TicketPortalSubmissionConfirmation::class, function (TicketPortalSubmissionConfirmation $notification, array $channels, $notifiable) {
        if (! $notifiable instanceof AnonymousNotifiable) {
            return false;
        }

        return in_array('mail', $channels, true)
            && $notifiable->routeNotificationFor('mail') === 'portal.user@example.com';
    });
});

it('E1-F1-I4 stores attachments uploaded through the portal API', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    $file = UploadedFile::fake()->create('evidence.pdf', 120, 'application/pdf');

    $response = post('/api/v1/portal/tickets', [
        'name' => 'Attachment User',
        'email' => 'attach@example.com',
        'subject' => 'Attachment Ticket',
        'message' => 'Please see the attached file for details.',
        'tags' => ['support'],
        'attachments' => [$file],
    ], array_merge(portalHeaders($tenant, $brand), ['Accept' => 'application/json']));

    $response->assertCreated();

    $submission = TicketSubmission::query()->with(['attachments', 'messageRecord.attachments'])->firstOrFail();
    expect($submission->attachments)->toHaveCount(1);
    expect($submission->messageRecord->attachments)->toHaveCount(1);

    $path = $submission->attachments->first()->path;
    Storage::disk('local')->assertExists($path);
});

it('E1-F1-I4 returns validation errors for incomplete portal submissions', function () {
    $tenant = Tenant::factory()->create();

    $response = postJson('/api/v1/portal/tickets', [
        'email' => 'invalid',
    ], portalHeaders($tenant));

    $response->assertStatus(422);
    $response->assertJsonPath('error.code', 'ERR_VALIDATION');
});

it('E1-F1-I4 enforces RBAC on ticket submission listings', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $admin->assignRole('Admin');
    $viewer = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $viewer->assignRole('Viewer');
    $outsider = User::factory()->create();

    TicketSubmission::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);

    actingAs($admin);
    getJson('/api/v1/ticket-submissions', portalHeaders($tenant, $brand))->assertOk();

    actingAs($viewer);
    getJson('/api/v1/ticket-submissions', portalHeaders($tenant, $brand))->assertOk();

    actingAs($outsider);
    getJson('/api/v1/ticket-submissions', portalHeaders($tenant, $brand))->assertForbidden();
});

it('E1-F1-I4 prevents cross-tenant access to ticket submissions', function () {
    $tenantA = Tenant::factory()->create();
    $brandA = Brand::factory()->create(['tenant_id' => $tenantA->id]);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenantA);
    $adminA = User::factory()->create(['tenant_id' => $tenantA->id, 'brand_id' => $brandA->id]);
    $adminA->assignRole('Admin');

    $submission = TicketSubmission::factory()->create(['tenant_id' => $tenantA->id, 'brand_id' => $brandA->id]);

    $tenantB = Tenant::factory()->create();
    $brandB = Brand::factory()->create(['tenant_id' => $tenantB->id]);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenantB);
    $adminB = User::factory()->create(['tenant_id' => $tenantB->id, 'brand_id' => $brandB->id]);
    $adminB->assignRole('Admin');

    actingAs($adminB);
    getJson(sprintf('/api/v1/ticket-submissions/%d', $submission->getKey()), portalHeaders($tenantB, $brandB))->assertNotFound();
});

it('E1-F1-I4 renders the portal ticket form with tenant context', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    $response = get('/portal/tickets/create', [
        'X-Tenant' => $tenant->slug,
        'X-Brand' => $brand->slug,
    ]);
    $response->assertOk();
    $response->assertSee('Submit a Support Ticket');
});
