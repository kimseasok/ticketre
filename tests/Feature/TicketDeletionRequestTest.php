<?php

use App\Jobs\ProcessTicketDeletionRequestJob;
use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Message;
use App\Models\Ticket;
use App\Models\TicketDeletionRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantRoleProvisioner;
use App\Services\TicketDeletionService;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function (): void {
    if (app()->bound('currentTenant')) {
        app()->forgetInstance('currentTenant');
    }

    if (app()->bound('currentBrand')) {
        app()->forgetInstance('currentBrand');
    }
});

function deletionHeaders(Tenant $tenant, ?Brand $brand = null): array
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

it('E2-F7-I3 processes ticket deletion workflow with approval hold', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);
    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);
    $admin->assignRole('Admin');

    $ticket = Ticket::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'assignee_id' => $admin->id,
        'metadata' => [],
    ]);

    $messagePublic = Message::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'ticket_id' => $ticket->id,
        'user_id' => $admin->id,
        'visibility' => Message::VISIBILITY_PUBLIC,
        'author_role' => Message::ROLE_AGENT,
    ]);

    $messageInternal = Message::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'ticket_id' => $ticket->id,
        'user_id' => $admin->id,
        'visibility' => Message::VISIBILITY_INTERNAL,
        'author_role' => Message::ROLE_AGENT,
    ]);

    Attachment::create([
        'tenant_id' => $tenant->id,
        'attachable_type' => Message::class,
        'attachable_id' => $messagePublic->id,
        'disk' => 'local',
        'path' => 'attachments/demo-message.txt',
        'size' => 512,
        'mime_type' => 'text/plain',
    ]);

    Attachment::create([
        'tenant_id' => $tenant->id,
        'attachable_type' => Ticket::class,
        'attachable_id' => $ticket->id,
        'disk' => 'local',
        'path' => 'attachments/demo-ticket.txt',
        'size' => 256,
        'mime_type' => 'text/plain',
    ]);

    actingAs($admin);

    $payload = [
        'ticket_id' => $ticket->id,
        'reason' => 'GDPR deletion request',
    ];

    $response = postJson('/api/v1/ticket-deletion-requests', $payload, array_merge(deletionHeaders($tenant, $brand), [
        'X-Correlation-ID' => 'demo-correlation-req',
    ]));

    $response->assertCreated();
    $response->assertJsonPath('data.correlation_id', 'demo-correlation-req');

    $requestModel = TicketDeletionRequest::query()->firstOrFail();

    Queue::fake();

    $approveResponse = postJson(
        sprintf('/api/v1/ticket-deletion-requests/%d/approve', $requestModel->id),
        ['hold_hours' => 0],
        deletionHeaders($tenant, $brand)
    );

    $approveResponse->assertOk();

    Queue::assertPushed(ProcessTicketDeletionRequestJob::class);

    (new ProcessTicketDeletionRequestJob($requestModel->id))->handle(app(TicketDeletionService::class));

    $requestModel->refresh();
    $ticket->refresh();

    expect($requestModel->status)->toBe(TicketDeletionRequest::STATUS_COMPLETED);
    expect($requestModel->processed_at)->not->toBeNull();
    expect($requestModel->aggregate_snapshot['messages']['total'] ?? null)->toBe(2);
    expect($requestModel->aggregate_snapshot['attachments']['total'] ?? null)->toBe(2);

    $ticket = Ticket::withTrashed()->findOrFail($ticket->id);

    expect($ticket->deleted_at)->not->toBeNull();
    expect($ticket->metadata['redacted'] ?? false)->toBeTrue();
    expect($ticket->metadata['ticket_deletion_request_id'] ?? null)->toBe($requestModel->id);

    $messages = Message::withTrashed()->where('ticket_id', $ticket->id)->get();

    expect($messages)->toHaveCount(2);
    $messages->each(function (Message $message): void {
        expect($message->body)->toBe('[REDACTED]');
        expect($message->deleted_at)->not->toBeNull();
    });

    $ticketAttachment = Attachment::withTrashed()
        ->where('attachable_type', Ticket::class)
        ->where('attachable_id', $ticket->id)
        ->first();

    expect($ticketAttachment)->not->toBeNull();
    expect($ticketAttachment->deleted_at)->not->toBeNull();

    $auditLog = AuditLog::query()
        ->where('action', 'ticket.redacted')
        ->where('auditable_type', Ticket::class)
        ->where('auditable_id', $ticket->id)
        ->first();

    expect($auditLog)->not->toBeNull();
});

it('E2-F7-I3 enforces RBAC for ticket deletion requests', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);
    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);
    $admin->assignRole('Admin');

    $agent = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);
    $agent->assignRole('Agent');

    $viewer = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);
    $viewer->assignRole('Viewer');

    $ticket = Ticket::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'assignee_id' => $admin->id,
        'metadata' => [],
    ]);

    actingAs($admin);

    $createResponse = postJson('/api/v1/ticket-deletion-requests', [
        'ticket_id' => $ticket->id,
        'reason' => 'Admin initiated deletion',
    ], deletionHeaders($tenant, $brand));

    $createResponse->assertCreated();

    $requestModel = TicketDeletionRequest::query()->firstOrFail();

    actingAs($agent);

    $agentCreate = postJson('/api/v1/ticket-deletion-requests', [
        'ticket_id' => $ticket->id,
        'reason' => 'Agent attempt',
    ], deletionHeaders($tenant, $brand));

    $agentCreate->assertForbidden();
    $agentCreate->assertJsonPath('error.code', 'ERR_HTTP_403');

    $agentApprove = postJson(
        sprintf('/api/v1/ticket-deletion-requests/%d/approve', $requestModel->id),
        [],
        deletionHeaders($tenant, $brand)
    );

    $agentApprove->assertForbidden();

    actingAs($viewer);

    $viewerCreate = postJson('/api/v1/ticket-deletion-requests', [
        'ticket_id' => $ticket->id,
        'reason' => 'Viewer attempt',
    ], deletionHeaders($tenant, $brand));

    $viewerCreate->assertForbidden();
    $viewerCreate->assertJsonPath('error.code', 'ERR_HTTP_403');

    $viewerApprove = postJson(
        sprintf('/api/v1/ticket-deletion-requests/%d/approve', $requestModel->id),
        [],
        deletionHeaders($tenant, $brand)
    );

    $viewerApprove->assertForbidden();
});

it('E2-F7-I3 validates tenant isolation and required data', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);
    $otherTenant = Tenant::factory()->create();

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);
    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);
    $admin->assignRole('Admin');

    $foreignTicket = Ticket::factory()->create([
        'tenant_id' => $otherTenant->id,
        'metadata' => [],
    ]);

    actingAs($admin);

    $response = postJson('/api/v1/ticket-deletion-requests', [
        'ticket_id' => $foreignTicket->id,
        'reason' => 'Invalid tenant ticket',
    ], deletionHeaders($tenant, $brand));

    $response->assertStatus(422);
    $response->assertJsonPath('error.code', 'ERR_VALIDATION');
});

it('E2-F7-I3 allows cancelling approved requests before hold expiry', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);
    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);
    $admin->assignRole('Admin');

    $ticket = Ticket::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'assignee_id' => $admin->id,
        'metadata' => [],
    ]);

    actingAs($admin);

    $createResponse = postJson('/api/v1/ticket-deletion-requests', [
        'ticket_id' => $ticket->id,
        'reason' => 'Hold and cancel',
    ], deletionHeaders($tenant, $brand));

    $createResponse->assertCreated();

    $requestModel = TicketDeletionRequest::query()->firstOrFail();

    $approveResponse = postJson(
        sprintf('/api/v1/ticket-deletion-requests/%d/approve', $requestModel->id),
        ['hold_hours' => 12],
        deletionHeaders($tenant, $brand)
    );

    $approveResponse->assertOk();

    $cancelResponse = postJson(
        sprintf('/api/v1/ticket-deletion-requests/%d/cancel', $requestModel->id),
        [],
        deletionHeaders($tenant, $brand)
    );

    $cancelResponse->assertOk();

    $requestModel->refresh();
    expect($requestModel->status)->toBe(TicketDeletionRequest::STATUS_CANCELLED);
    expect($requestModel->cancelled_at)->not->toBeNull();
});

it('E2-F7-I3 lists ticket deletion requests within tenant scope', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);
    $otherTenant = Tenant::factory()->create();

    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);
    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);

    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);
    $admin->assignRole('Admin');

    $ticket = Ticket::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'assignee_id' => $admin->id,
        'metadata' => [],
    ]);

    $foreignTicket = Ticket::factory()->create([
        'tenant_id' => $otherTenant->id,
        'metadata' => [],
    ]);

    TicketDeletionRequest::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'ticket_id' => $ticket->id,
        'requested_by' => $admin->id,
    ]);

    TicketDeletionRequest::factory()->create([
        'tenant_id' => $otherTenant->id,
        'ticket_id' => $foreignTicket->id,
    ]);

    actingAs($admin);

    $response = getJson('/api/v1/ticket-deletion-requests', deletionHeaders($tenant, $brand));

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.tenant_id', $tenant->id);

    $statusFilter = getJson('/api/v1/ticket-deletion-requests?status=pending', deletionHeaders($tenant, $brand));
    $statusFilter->assertOk();
    $statusFilter->assertJsonCount(1, 'data');
});
