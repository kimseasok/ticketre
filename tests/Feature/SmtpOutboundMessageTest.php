<?php

use App\Exceptions\SmtpPermanentFailureException;
use App\Jobs\DispatchSmtpMessageJob;
use App\Models\Brand;
use App\Models\Message;
use App\Models\SmtpOutboundMessage;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TwoFactorCredential;
use App\Models\User;
use App\Services\SmtpOutboundMessageService;
use App\Services\TenantRoleProvisioner;
use Illuminate\Contracts\Mail\Factory as MailFactory;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

beforeEach(function (): void {
    if (app()->bound('currentTenant')) {
        app()->forgetInstance('currentTenant');
    }

    if (app()->bound('currentBrand')) {
        app()->forgetInstance('currentBrand');
    }

    Queue::fake();

    app()->singleton(MailFactory::class, function (): MailFactory {
        /** @var MailFactory&\Mockery\LegacyMockInterface $mock */
        $mock = \Mockery::mock(MailFactory::class);
        $mock->shouldIgnoreMissing();

        return $mock;
    });
});

afterEach(function (): void {
    \Mockery::close();
});

/**
 * @return array<string, string>
 */
function smtpHeaders(Tenant $tenant, ?Brand $brand = null): array
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

function smtpVerifyTwoFactor(User $user): void
{
    session()->put('two_factor_verified_'.$user->getKey(), now()->addMinutes(30)->toIso8601String());
}

it('E6-F2-I2 #375 allows admins to queue smtp outbound messages via API', function (): void {
    /** @var Tenant $tenant */
    $tenant = Tenant::factory()->create();
    /** @var Brand $brand */
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    /** @var User $admin */
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $admin->assignRole('Admin');
    actingAs($admin);

    TwoFactorCredential::factory()->confirmed()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'user_id' => $admin->id,
    ]);
    smtpVerifyTwoFactor($admin);

    /** @var Ticket $ticket */
    $ticket = Ticket::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);

    $payload = [
        'ticket_id' => $ticket->id,
        'subject' => 'Demo subject',
        'body_text' => 'Plain body',
        'from_email' => 'support@'.$brand->domain,
        'to' => [
            ['email' => 'customer@example.com', 'name' => 'Customer'],
        ],
        'attachments' => [
            ['disk' => 'public', 'path' => 'tickets/'.$ticket->id.'/demo.txt', 'name' => 'demo.txt'],
        ],
    ];

    $response = postJson('/api/v1/smtp-outbound-messages', $payload, array_merge(smtpHeaders($tenant, $brand), [
        'X-Correlation-ID' => (string) Str::uuid(),
    ]));

    $response->assertCreated();
    $response->assertJsonPath('data.attributes.status', SmtpOutboundMessage::STATUS_QUEUED);

    $correlation = $response->headers->get('X-Correlation-ID');
    expect($correlation)->not()->toBeNull();
    $response->assertJsonPath('meta.correlation_id', $correlation);

    Queue::assertPushed(DispatchSmtpMessageJob::class);

    expect(SmtpOutboundMessage::where('ticket_id', $ticket->id)->where('subject', 'Demo subject')->exists())->toBeTrue();
});

it('E6-F2-I2 #375 prevents viewers from queuing smtp outbound messages', function (): void {
    /** @var Tenant $tenant */
    $tenant = Tenant::factory()->create();
    /** @var Brand $brand */
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    /** @var User $viewer */
    $viewer = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $viewer->assignRole('Viewer');
    actingAs($viewer);

    TwoFactorCredential::factory()->confirmed()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'user_id' => $viewer->id,
    ]);
    smtpVerifyTwoFactor($viewer);

    /** @var Ticket $ticket */
    $ticket = Ticket::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);

    $response = postJson('/api/v1/smtp-outbound-messages', [
        'ticket_id' => $ticket->id,
        'subject' => 'Blocked attempt',
        'body_text' => 'Should fail',
        'from_email' => 'support@'.$brand->domain,
        'to' => [
            ['email' => 'customer@example.com'],
        ],
    ], smtpHeaders($tenant, $brand));

    $response->assertStatus(Response::HTTP_FORBIDDEN);
    $response->assertJsonPath('error.code', 'ERR_HTTP_403');
});

it('E6-F2-I2 #375 validates recipient list when queuing messages', function (): void {
    /** @var Tenant $tenant */
    $tenant = Tenant::factory()->create();
    /** @var Brand $brand */
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    /** @var User $admin */
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $admin->assignRole('Admin');
    actingAs($admin);

    TwoFactorCredential::factory()->confirmed()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'user_id' => $admin->id,
    ]);
    smtpVerifyTwoFactor($admin);

    /** @var Ticket $ticket */
    $ticket = Ticket::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);

    $response = postJson('/api/v1/smtp-outbound-messages', [
        'ticket_id' => $ticket->id,
        'subject' => 'Missing recipients',
        'body_text' => 'No recipients provided',
        'from_email' => 'support@'.$brand->domain,
    ], smtpHeaders($tenant, $brand));

    $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    $response->assertJsonPath('error.code', 'ERR_VALIDATION');
});

it('E6-F2-I2 #375 scopes smtp outbound listings to the tenant', function (): void {
    /** @var Tenant $tenant */
    $tenant = Tenant::factory()->create();
    /** @var Brand $brand */
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);
    /** @var Tenant $otherTenant */
    $otherTenant = Tenant::factory()->create();
    /** @var Brand $otherBrand */
    $otherBrand = Brand::factory()->create(['tenant_id' => $otherTenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    /** @var User $agent */
    $agent = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $agent->assignRole('Agent');
    actingAs($agent);

    TwoFactorCredential::factory()->confirmed()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'user_id' => $agent->id,
    ]);
    smtpVerifyTwoFactor($agent);

    /** @var Ticket $tenantTicket */
    $tenantTicket = Ticket::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);

    /** @var Ticket $otherTicket */
    $otherTicket = Ticket::factory()->create([
        'tenant_id' => $otherTenant->id,
        'brand_id' => $otherBrand->id,
    ]);

    /** @var SmtpOutboundMessage $tenantOutbound */
    $tenantOutbound = SmtpOutboundMessage::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'ticket_id' => $tenantTicket->getKey(),
        'status' => SmtpOutboundMessage::STATUS_QUEUED,
        'from_email' => 'support@'.$brand->domain,
        'from_name' => $brand->name.' Support',
    ]);

    /** @var SmtpOutboundMessage $otherOutbound */
    $otherOutbound = SmtpOutboundMessage::factory()->create([
        'tenant_id' => $otherTenant->id,
        'brand_id' => $otherBrand->id,
        'ticket_id' => $otherTicket->getKey(),
        'status' => SmtpOutboundMessage::STATUS_SENT,
        'from_email' => 'support@'.$otherBrand->domain,
        'from_name' => $otherBrand->name.' Support',
    ]);

    $response = getJson('/api/v1/smtp-outbound-messages', smtpHeaders($tenant, $brand));

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.attributes.brand_id', $brand->id);
});

it('E6-F2-I2 #375 allows admins to update queued smtp outbound messages', function (): void {
    /** @var Tenant $tenant */
    $tenant = Tenant::factory()->create();
    /** @var Brand $brand */
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    /** @var User $admin */
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $admin->assignRole('Admin');
    actingAs($admin);

    TwoFactorCredential::factory()->confirmed()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'user_id' => $admin->id,
    ]);
    smtpVerifyTwoFactor($admin);

    /** @var Ticket $ticket */
    /** @var Ticket $ticket */
    $ticket = Ticket::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);

    /** @var SmtpOutboundMessage $outbound */
    $outbound = SmtpOutboundMessage::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'ticket_id' => $ticket->getKey(),
        'subject' => 'Original subject',
        'status' => SmtpOutboundMessage::STATUS_QUEUED,
        'from_email' => 'support@'.$brand->domain,
        'from_name' => $brand->name.' Support',
    ]);

    $response = patchJson(
        '/api/v1/smtp-outbound-messages/'.$outbound->getKey(),
        ['subject' => 'Updated subject line'],
        array_merge(smtpHeaders($tenant, $brand), ['X-Correlation-ID' => (string) Str::uuid()])
    );

    $response->assertOk();
    $response->assertJsonPath('data.attributes.subject', 'Updated subject line');

    $correlation = $response->headers->get('X-Correlation-ID');
    expect($correlation)->not()->toBeNull();
    $response->assertJsonPath('meta.correlation_id', $correlation);
});

it('E6-F2-I2 #375 allows admins to delete queued smtp outbound messages', function (): void {
    /** @var Tenant $tenant */
    $tenant = Tenant::factory()->create();
    /** @var Brand $brand */
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);
    app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

    /** @var User $admin */
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $admin->assignRole('Admin');
    actingAs($admin);

    TwoFactorCredential::factory()->confirmed()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'user_id' => $admin->id,
    ]);
    smtpVerifyTwoFactor($admin);

    /** @var Ticket $ticket */
    /** @var Ticket $ticket */
    $ticket = Ticket::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);

    /** @var SmtpOutboundMessage $outbound */
    $outbound = SmtpOutboundMessage::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'ticket_id' => $ticket->getKey(),
        'status' => SmtpOutboundMessage::STATUS_QUEUED,
        'from_email' => 'support@'.$brand->domain,
        'from_name' => $brand->name.' Support',
    ]);

    $response = deleteJson(
        '/api/v1/smtp-outbound-messages/'.$outbound->getKey(),
        [],
        array_merge(smtpHeaders($tenant, $brand), ['X-Correlation-ID' => (string) Str::uuid()])
    );

    $response->assertNoContent();
    expect($response->headers->get('X-Correlation-ID'))->not()->toBeNull();
    /** @var SmtpOutboundMessage|null $deleted */
    $deleted = SmtpOutboundMessage::withTrashed()->find($outbound->getKey());
    expect($deleted)->not()->toBeNull();
    expect($deleted?->trashed())->toBeTrue();
});

it('E6-F2-I2 #375 marks outbound messages as sent when the transport succeeds', function (): void {
    /** @var Tenant $tenant */
    $tenant = Tenant::factory()->create();
    /** @var Brand $brand */
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);
    /** @var Ticket $ticket */
    $ticket = Ticket::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);
    /** @var Message $message */
    $message = Message::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'ticket_id' => $ticket->getKey(),
    ]);

    /** @var SmtpOutboundMessage $outbound */
    /** @var SmtpOutboundMessage $outbound */
    /** @var SmtpOutboundMessage $outbound */
    $outbound = SmtpOutboundMessage::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'ticket_id' => $ticket->getKey(),
        'message_id' => $message->getKey(),
        'status' => SmtpOutboundMessage::STATUS_QUEUED,
        'attachments' => [],
        'to' => [
            ['email' => 'recipient@example.com', 'name' => 'Recipient'],
        ],
        'from_email' => 'support@'.$brand->domain,
        'from_name' => $brand->name.' Support',
    ]);

    /** @var MailFactory&\Mockery\LegacyMockInterface $factory */
    $factory = \Mockery::mock(MailFactory::class);
    /** @var Mailer&\Mockery\LegacyMockInterface $mailer */
    $mailer = \Mockery::mock(Mailer::class);

    /** @var \Mockery\Expectation $mailerExpectation */
    $mailerExpectation = $factory->shouldReceive('mailer');
    $mailerExpectation->once()->with('smtp')->andReturn($mailer);

    /** @var \Mockery\Expectation $sendExpectation */
    $sendExpectation = $mailer->shouldReceive('send');
    $sendExpectation->once();

    app()->instance(MailFactory::class, $factory);

    $job = new DispatchSmtpMessageJob($outbound->getKey(), (string) Str::uuid());
    $job->handle(app(SmtpOutboundMessageService::class));

    $outbound->refresh();
    /** @var SmtpOutboundMessage $reloaded */
    $reloaded = $outbound->fresh();

    expect($reloaded->status)->toBe(SmtpOutboundMessage::STATUS_SENT);
    expect($reloaded->delivered_at)->not()->toBeNull();
    expect($reloaded->attempts)->toBeGreaterThanOrEqual(1);
});

it('E6-F2-I2 #375 retries transient SMTP failures', function (): void {
    /** @var Tenant $tenant */
    $tenant = Tenant::factory()->create();
    /** @var Brand $brand */
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);
    /** @var Ticket $ticket */
    $ticket = Ticket::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);
    /** @var Message $message */
    $message = Message::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'ticket_id' => $ticket->getKey(),
    ]);

    $outbound = SmtpOutboundMessage::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'ticket_id' => $ticket->getKey(),
        'message_id' => $message->getKey(),
        'status' => SmtpOutboundMessage::STATUS_QUEUED,
        'attachments' => [],
        'to' => [
            ['email' => 'recipient@example.com'],
        ],
        'from_email' => 'support@'.$brand->domain,
        'from_name' => $brand->name.' Support',
    ]);

    /** @var MailFactory&\Mockery\LegacyMockInterface $factory */
    $factory = \Mockery::mock(MailFactory::class);
    /** @var Mailer&\Mockery\LegacyMockInterface $mailer */
    $mailer = \Mockery::mock(Mailer::class);

    /** @var \Mockery\Expectation $retryMailerExpectation */
    $retryMailerExpectation = $factory->shouldReceive('mailer');
    $retryMailerExpectation->once()->with('smtp')->andReturn($mailer);

    /** @var \Mockery\Expectation $retrySendExpectation */
    $retrySendExpectation = $mailer->shouldReceive('send');
    $retrySendExpectation->once()->andThrow(new RuntimeException('smtp unavailable'));

    app()->instance(MailFactory::class, $factory);

    $job = new DispatchSmtpMessageJob($outbound->getKey(), (string) Str::uuid());

    try {
        $job->handle(app(SmtpOutboundMessageService::class));
    } catch (RuntimeException $exception) {
        // expected for transient retry
    }

    $outbound->refresh();
    /** @var SmtpOutboundMessage $retryLoaded */
    $retryLoaded = $outbound->fresh();

    expect($retryLoaded->status)->toBe(SmtpOutboundMessage::STATUS_RETRYING);
    expect($retryLoaded->last_error)->not()->toBeNull();
    expect($retryLoaded->failed_at)->toBeNull();
});

it('E6-F2-I2 #375 marks permanent SMTP failures without rethrowing', function (): void {
    /** @var Tenant $tenant */
    $tenant = Tenant::factory()->create();
    /** @var Brand $brand */
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);

    app()->instance('currentTenant', $tenant);
    app()->instance('currentBrand', $brand);
    /** @var Ticket $ticket */
    $ticket = Ticket::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
    ]);
    /** @var Message $message */
    $message = Message::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'ticket_id' => $ticket->getKey(),
    ]);

    $outbound = SmtpOutboundMessage::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'ticket_id' => $ticket->getKey(),
        'message_id' => $message->getKey(),
        'status' => SmtpOutboundMessage::STATUS_QUEUED,
        'attachments' => [],
        'to' => [
            ['email' => 'recipient@example.com'],
        ],
        'from_email' => 'support@'.$brand->domain,
        'from_name' => $brand->name.' Support',
    ]);

    /** @var MailFactory&\Mockery\LegacyMockInterface $factory */
    $factory = \Mockery::mock(MailFactory::class);
    /** @var Mailer&\Mockery\LegacyMockInterface $mailer */
    $mailer = \Mockery::mock(Mailer::class);

    /** @var \Mockery\Expectation $failureMailerExpectation */
    $failureMailerExpectation = $factory->shouldReceive('mailer');
    $failureMailerExpectation->once()->with('smtp')->andReturn($mailer);

    /** @var \Mockery\Expectation $failureSendExpectation */
    $failureSendExpectation = $mailer->shouldReceive('send');
    $failureSendExpectation->once()->andThrow(new SmtpPermanentFailureException('invalid recipient'));

    app()->instance(MailFactory::class, $factory);

    $job = new DispatchSmtpMessageJob($outbound->getKey(), (string) Str::uuid());
    $job->handle(app(SmtpOutboundMessageService::class));

    $outbound->refresh();
    /** @var SmtpOutboundMessage $failedLoaded */
    $failedLoaded = $outbound->fresh();

    expect($failedLoaded->status)->toBe(SmtpOutboundMessage::STATUS_FAILED);
    expect($failedLoaded->failed_at)->not()->toBeNull();
});
