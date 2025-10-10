<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\BrandDomain;
use App\Models\CiQualityGate;
use App\Models\ObservabilityPipeline;
use App\Models\ObservabilityStack;
use App\Models\PermissionCoverageReport;
use App\Models\BroadcastConnection;
use App\Models\Company;
use App\Models\Contact;
use App\Models\ContactAnonymizationRequest;
use App\Models\KbCategory;
use App\Models\HorizonDeployment;
use App\Models\Message;
use App\Models\RedisConfiguration;
use App\Models\Tenant;
use App\Models\Team;
use App\Models\TeamMembership;
use App\Models\Ticket;
use App\Models\TicketRelationship;
use App\Models\TicketDeletionRequest;
use App\Models\TicketEvent;
use App\Models\TicketSubmission;
use App\Models\TicketWorkflow;
use App\Models\TicketWorkflowState;
use App\Models\TicketWorkflowTransition;
use App\Models\User;
use App\Services\CompanyService;
use App\Services\ContactService;
use App\Services\KbArticleService;
use App\Services\TenantRoleProvisioner;
use App\Services\TicketLifecycleBroadcaster;
use App\Services\PortalTicketSubmissionService;
use App\Services\TicketRelationshipService;
use App\Services\TicketMergeService;
use App\Services\TicketService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * DO NOT USE IN PRODUCTION. Demo data only.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Demo Tenant',
            'slug' => 'demo-tenant',
            'domain' => 'demo.localhost',
        ]);

        app()->instance('currentTenant', $tenant);

        app(TenantRoleProvisioner::class)->syncSystemRoles($tenant);

        $brand = Brand::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Demo Brand',
            'slug' => 'demo-brand',
            'domain' => 'brand.demo.localhost',
        ]);

        app()->instance('currentBrand', $brand);

        BrandDomain::factory()->verified()->create([
            'tenant_id' => $tenant->id,
            'brand_id' => $brand->id,
            'domain' => 'support.demo.localhost',
            'verification_token' => 'demo-token',
        ]);

        CiQualityGate::create([
            'tenant_id' => $tenant->id,
            'brand_id' => $brand->id,
            'name' => 'Demo CI Quality Gate',
            'slug' => 'demo-ci-quality-gate',
            'coverage_threshold' => 85,
            'max_critical_vulnerabilities' => 0,
            'max_high_vulnerabilities' => 0,
            'enforce_dependency_audit' => true,
            'enforce_docker_build' => true,
            'notifications_enabled' => true,
            'notify_channel' => '#demo-ci-alerts',
            'metadata' => [
                'owner' => 'platform-demo',
                'description' => 'NON-PRODUCTION seed to illustrate CI enforcement policies.',
            ],
        ]);

        ObservabilityPipeline::create([
            'tenant_id' => $tenant->id,
            'brand_id' => $brand->id,
            'name' => 'Demo Logging Pipeline',
            'slug' => 'demo-logging-pipeline',
            'pipeline_type' => 'logs',
            'ingest_endpoint' => 'https://logs.demo.localhost/ingest',
            'ingest_protocol' => 'https',
            'buffer_strategy' => 'disk',
            'buffer_retention_seconds' => 900,
            'retry_backoff_seconds' => 30,
            'max_retry_attempts' => 5,
            'batch_max_bytes' => 1048576,
            'metrics_scrape_interval_seconds' => null,
            'metadata' => [
                'description' => 'NON-PRODUCTION pipeline for demo observability flows.',
            ],
        ]);


        PermissionCoverageReport::create([
            'tenant_id' => $tenant->id,
            'brand_id' => $brand->id,
            'module' => 'api',
            'notes' => 'NON-PRODUCTION snapshot seeded for CI demos.',
            'metadata' => [
                'source' => 'demo-seeder',
                'build_reference' => 'demo-ci',
            ],
        ]);

        RedisConfiguration::create([
            'tenant_id' => $tenant->id,
            'brand_id' => $brand->id,
            'name' => 'Demo Redis Cluster',
            'slug' => 'demo-redis-cluster',
            'cache_connection_name' => 'cache',
            'cache_host' => 'redis',
            'cache_port' => 6379,
            'cache_database' => 1,
            'cache_tls' => false,
            'cache_prefix' => 'demo_tenant_cache',
            'session_connection_name' => 'default',
            'session_host' => 'redis',
            'session_port' => 6379,
            'session_database' => 0,
            'session_tls' => false,
            'session_lifetime_minutes' => 120,
            'use_for_cache' => true,
            'use_for_sessions' => true,
            'fallback_store' => 'file',
            'cache_auth_secret' => encrypt('NON-PRODUCTION:cache-secret'),
            'session_auth_secret' => encrypt('NON-PRODUCTION:session-secret'),
            'options' => [
                'cluster' => 'redis',
            ],
        ]);

        ObservabilityStack::create([
            'tenant_id' => $tenant->id,
            'brand_id' => $brand->id,
            'name' => 'Demo Observability Stack',
            'slug' => 'demo-observability-stack',
            'status' => 'selected',
            'logs_tool' => 'loki-grafana',
            'metrics_tool' => 'prometheus',
            'alerts_tool' => 'grafana-alerting',
            'log_retention_days' => 30,
            'metric_retention_days' => 30,
            'trace_retention_days' => 14,
            'estimated_monthly_cost' => 650.00,
            'trace_sampling_strategy' => 'probabilistic 20%',
            'decision_matrix' => [
                [
                    'option' => 'ELK',
                    'monthly_cost' => 1100.00,
                    'scalability' => 'Requires dedicated data nodes and snapshot automation.',
                    'notes' => 'Higher cost but best for log analytics depth.',
                ],
                [
                    'option' => 'Loki/Grafana',
                    'monthly_cost' => 650.00,
                    'scalability' => 'Object storage backed, horizontally scalable queriers.',
                    'notes' => 'Selected for demo due to cost and multi-tenant isolation.',
                ],
            ],
            'security_notes' => 'NON-PRODUCTION seed illustrating TLS ingestion and RBAC enforcement.',
            'compliance_notes' => 'Retention aligned with GDPR data minimization guidance.',
            'metadata' => [
                'owner' => 'platform-observability',
                'environment' => 'demo',
            ],
        ]);

        HorizonDeployment::create([
            'tenant_id' => $tenant->id,
            'brand_id' => $brand->id,
            'name' => 'Demo Horizon Deployment',
            'slug' => 'demo-horizon-deployment',
            'domain' => 'horizon.demo.localhost',
            'auth_guard' => 'admin',
            'horizon_connection' => 'sync',
            'uses_tls' => false,
            'supervisors' => [
                [
                    'name' => 'demo-app-supervisor',
                    'connection' => 'sync',
                    'queue' => ['default'],
                    'balance' => 'auto',
                    'min_processes' => 1,
                    'max_processes' => 4,
                    'max_jobs' => 0,
                    'max_time' => 0,
                    'timeout' => 60,
                    'tries' => 1,
                ],
                [
                    'name' => 'demo-priority-supervisor',
                    'connection' => 'sync',
                    'queue' => ['priority', 'emails'],
                    'balance' => 'simple',
                    'min_processes' => 1,
                    'max_processes' => 2,
                    'max_jobs' => 0,
                    'max_time' => 0,
                    'timeout' => 90,
                    'tries' => 3,
                ],
            ],
            'last_deployed_at' => now()->subDay(),
            'last_health_status' => 'ok',
            'last_health_checked_at' => now()->subMinutes(30),
            'last_health_report' => [
                'connection' => 'sync',
                'supervisors' => [
                    ['name' => 'demo-app-supervisor', 'issues' => []],
                    ['name' => 'demo-priority-supervisor', 'issues' => []],
                ],
                'issues' => [],
                'duration_ms' => 12.5,
            ],
            'metadata' => [
                'owner' => 'platform-queues',
                'notes' => 'NON-PRODUCTION Horizon deployment for dashboard demo.',
            ],
        ]);

        $workflow = TicketWorkflow::create([
            'tenant_id' => $tenant->id,
            'brand_id' => $brand->id,
            'name' => 'Default Support Workflow',
            'slug' => 'default-support',
            'description' => 'NON-PRODUCTION demo workflow for seeded tickets.',
            'is_default' => true,
        ]);

        $newState = TicketWorkflowState::create([
            'tenant_id' => $tenant->id,
            'brand_id' => $brand->id,
            'ticket_workflow_id' => $workflow->getKey(),
            'name' => 'New',
            'slug' => 'new',
            'position' => 0,
            'is_initial' => true,
            'is_terminal' => false,
            'sla_minutes' => 480,
            'description' => 'Initial intake state for demo purposes only.',
        ]);

        $triageState = TicketWorkflowState::create([
            'tenant_id' => $tenant->id,
            'brand_id' => $brand->id,
            'ticket_workflow_id' => $workflow->getKey(),
            'name' => 'Triage',
            'slug' => 'triage',
            'position' => 1,
            'is_initial' => false,
            'is_terminal' => false,
            'sla_minutes' => 240,
            'description' => 'NON-PRODUCTION triage state.',
        ]);

        $resolvedState = TicketWorkflowState::create([
            'tenant_id' => $tenant->id,
            'brand_id' => $brand->id,
            'ticket_workflow_id' => $workflow->getKey(),
            'name' => 'Resolved',
            'slug' => 'resolved',
            'position' => 2,
            'is_initial' => false,
            'is_terminal' => true,
            'sla_minutes' => null,
            'description' => 'Terminal resolution state (NON-PRODUCTION).',
        ]);

        TicketWorkflowTransition::create([
            'tenant_id' => $tenant->id,
            'brand_id' => $brand->id,
            'ticket_workflow_id' => $workflow->getKey(),
            'from_state_id' => $newState->getKey(),
            'to_state_id' => $triageState->getKey(),
            'requires_comment' => false,
            'metadata' => ['seed_source' => 'demo'],
        ]);

        TicketWorkflowTransition::create([
            'tenant_id' => $tenant->id,
            'brand_id' => $brand->id,
            'ticket_workflow_id' => $workflow->getKey(),
            'from_state_id' => $triageState->getKey(),
            'to_state_id' => $resolvedState->getKey(),
            'requires_comment' => true,
            'metadata' => ['seed_source' => 'demo'],
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'brand_id' => $brand->id,
            'name' => 'Demo Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);
        $admin->assignRole('Admin');

        $agent = User::factory()->create([
            'tenant_id' => $tenant->id,
            'brand_id' => $brand->id,
            'name' => 'Demo Agent',
            'email' => 'agent@example.com',
            'password' => Hash::make('password'),
        ]);
        $agent->assignRole('Agent');

        $viewer = User::factory()->create([
            'tenant_id' => $tenant->id,
            'brand_id' => $brand->id,
            'name' => 'Demo Viewer',
            'email' => 'viewer@example.com',
            'password' => Hash::make('password'),
        ]);
        $viewer->assignRole('Viewer');

        $tierOneTeam = Team::create([
            'tenant_id' => $tenant->id,
            'brand_id' => $brand->id,
            'name' => 'Tier 1 Support',
            'slug' => 'tier-1-support',
            'default_queue' => 'inbox',
            'description' => 'NON-PRODUCTION frontline support team for demo workloads.',
        ]);

        $tierOneTeam->memberships()->create([
            'tenant_id' => $tenant->id,
            'brand_id' => $brand->id,
            'user_id' => $admin->id,
            'role' => TeamMembership::ROLE_LEAD,
            'is_primary' => true,
            'joined_at' => now()->subDays(45),
        ]);

        $tierOneTeam->memberships()->create([
            'tenant_id' => $tenant->id,
            'brand_id' => $brand->id,
            'user_id' => $agent->id,
            'role' => TeamMembership::ROLE_MEMBER,
            'is_primary' => true,
            'joined_at' => now()->subDays(20),
        ]);

        $vipTeam = Team::create([
            'tenant_id' => $tenant->id,
            'brand_id' => $brand->id,
            'name' => 'VIP Success',
            'slug' => 'vip-success',
            'default_queue' => 'vip',
            'description' => 'NON-PRODUCTION escalation pod for high-value customers.',
        ]);

        $vipTeam->memberships()->create([
            'tenant_id' => $tenant->id,
            'brand_id' => $brand->id,
            'user_id' => $admin->id,
            'role' => TeamMembership::ROLE_LEAD,
            'is_primary' => false,
            'joined_at' => now()->subDays(10),
        ]);

        $companyService = app(CompanyService::class);
        $contactService = app(ContactService::class);

        $company = $companyService->create([
            'name' => 'Demo Company',
            'brand_id' => $brand->id,
            'tags' => ['vip', 'demo'],
            'metadata' => [
                'tier' => 'NON-PRODUCTION',
            ],
        ], $admin, (string) Str::uuid());

        $contact = $contactService->create([
            'name' => 'Demo Contact',
            'email' => 'contact@example.com',
            'phone' => '+15550000001',
            'company_id' => $company->getKey(),
            'brand_id' => $brand->id,
            'tags' => ['demo', 'vip'],
            'metadata' => ['source' => 'NON-PRODUCTION seed'],
            'gdpr_marketing_opt_in' => true,
            'gdpr_data_processing_opt_in' => true,
        ], $admin, (string) Str::uuid());

        $rootCategory = KbCategory::factory()->create([
            'tenant_id' => $tenant->id,
            'brand_id' => $brand->id,
            'name' => 'Getting Started',
            'slug' => 'getting-started',
            'order' => 1,
        ]);

        $childCategory = KbCategory::factory()->create([
            'tenant_id' => $tenant->id,
            'brand_id' => $brand->id,
            'parent_id' => $rootCategory->id,
            'name' => 'Troubleshooting',
            'slug' => 'troubleshooting',
            'order' => 2,
        ]);

        $articleService = app(KbArticleService::class);

        $articleService->create([
            'brand_id' => $brand->id,
            'category_id' => $rootCategory->id,
            'slug' => 'welcome',
            'default_locale' => 'en',
            'author_id' => $agent->id,
            'translations' => [
                [
                    'locale' => 'en',
                    'title' => 'Welcome to the Service Desk',
                    'status' => 'published',
                    'content' => '<p>This is demo content. DO NOT USE IN PRODUCTION.</p>',
                    'excerpt' => 'Orientation article for the NON-PRODUCTION demo knowledge base.',
                    'metadata' => ['tags' => ['welcome', 'demo']],
                ],
                [
                    'locale' => 'es',
                    'title' => 'Bienvenido al Service Desk',
                    'status' => 'published',
                    'content' => '<p>Contenido de demostración. NO USAR EN PRODUCCIÓN.</p>',
                    'excerpt' => 'Artículo de orientación para la demostración (SOLO NO PRODUCCIÓN).',
                    'metadata' => ['tags' => ['bienvenida', 'demo']],
                ],
            ],
        ], $agent);

        $articleService->create([
            'brand_id' => $brand->id,
            'category_id' => $childCategory->id,
            'slug' => 'reset-password',
            'default_locale' => 'en',
            'author_id' => $agent->id,
            'translations' => [
                [
                    'locale' => 'en',
                    'title' => 'Resetting Your Password',
                    'status' => 'draft',
                    'content' => '<p>Demo reset instructions. DO NOT USE IN PRODUCTION.</p>',
                    'excerpt' => 'Internal draft instructions for resetting credentials.',
                    'metadata' => ['tags' => ['credentials', 'internal']],
                ],
            ],
        ], $agent);

        $ticket = Ticket::factory()->create([
            'tenant_id' => $tenant->id,
            'brand_id' => $brand->id,
            'ticket_workflow_id' => $workflow->getKey(),
            'company_id' => $company->id,
            'contact_id' => $contact->id,
            'assignee_id' => $agent->id,
            'subject' => 'Demo ticket',
            'status' => 'open',
            'custom_fields' => [
                [
                    'key' => 'environment',
                    'type' => 'string',
                    'value' => 'NON-PRODUCTION',
                ],
            ],
        ]);

        $duplicateTicket = Ticket::factory()->create([
            'tenant_id' => $tenant->id,
            'brand_id' => $brand->id,
            'ticket_workflow_id' => $workflow->getKey(),
            'company_id' => $company->id,
            'contact_id' => $contact->id,
            'assignee_id' => $agent->id,
            'subject' => 'Demo duplicate ticket',
            'status' => 'pending',
        ]);

        Message::factory()->for($ticket)->create([
            'tenant_id' => $tenant->id,
            'brand_id' => $brand->id,
            'user_id' => $agent->id,
            'author_role' => Message::ROLE_AGENT,
            'visibility' => Message::VISIBILITY_INTERNAL,
            'body' => 'Internal note seeded for demo. DO NOT USE IN PRODUCTION.',
        ]);

        Message::factory()->for($ticket)->create([
            'tenant_id' => $tenant->id,
            'brand_id' => $brand->id,
            'user_id' => $agent->id,
            'author_role' => Message::ROLE_AGENT,
            'visibility' => Message::VISIBILITY_PUBLIC,
            'body' => 'Public reply seeded for demo. DO NOT USE IN PRODUCTION.',
        ]);

        app(TicketRelationshipService::class)->create([
            'primary_ticket_id' => $ticket->getKey(),
            'related_ticket_id' => $duplicateTicket->getKey(),
            'relationship_type' => TicketRelationship::TYPE_DUPLICATE,
            'context' => ['note' => 'NON-PRODUCTION duplicate reference'],
            'correlation_id' => (string) Str::uuid(),
        ], $admin);

        app(PortalTicketSubmissionService::class)->submit([
            'name' => 'Portal Demo Contact',
            'email' => 'portal.demo@example.com',
            'subject' => 'NON-PRODUCTION portal submission',
            'message' => 'This ticket submission is seeded for demo purposes only. DO NOT USE IN PRODUCTION.',
            'tags' => ['demo', 'support'],
            'ip_address' => '198.51.100.42',
            'user_agent' => 'Demo Seed Browser/1.0',
        ], [], (string) Str::uuid());

        BroadcastConnection::factory()->create([
            'tenant_id' => $tenant->id,
            'brand_id' => $brand->id,
            'user_id' => $agent->id,
            'channel_name' => sprintf('tenants.%d.brands.%d.tickets', $tenant->id, $brand->id),
            'status' => BroadcastConnection::STATUS_ACTIVE,
            'latency_ms' => 32,
            'metadata' => [
                'note' => 'NON-PRODUCTION demo connection monitor',
            ],
            'last_seen_at' => now(),
            'correlation_id' => (string) Str::uuid(),
        ]);

        $ticketService = app(TicketService::class);
        $ticketService->update($ticket, ['priority' => 'high', 'workflow_state' => 'triage'], $admin);
        $contactService->update($contact, ['phone' => '+15550000000'], $admin);

        $gdprContact = $contactService->create([
            'name' => 'GDPR Demo Contact',
            'email' => 'gdpr-demo@example.com',
            'company_id' => $company->getKey(),
            'brand_id' => $brand->id,
            'tags' => ['gdpr'],
            'metadata' => ['seed_source' => 'NON-PRODUCTION DEMO'],
            'gdpr_marketing_opt_in' => true,
            'gdpr_data_processing_opt_in' => true,
        ], $admin, (string) Str::uuid());

        $anonymizationRequest = ContactAnonymizationRequest::create([
            'tenant_id' => $tenant->id,
            'brand_id' => $brand->id,
            'contact_id' => $gdprContact->getKey(),
            'requested_by' => $admin->getKey(),
            'status' => ContactAnonymizationRequest::STATUS_COMPLETED,
            'reason' => 'NON-PRODUCTION demo anonymization request.',
            'correlation_id' => (string) Str::uuid(),
            'pseudonym' => 'Anonymized Contact DEMO',
            'processed_at' => now(),
        ]);

        $gdprContact->update([
            'name' => $anonymizationRequest->pseudonym,
            'email' => sprintf('anon-%s@redacted.local', $anonymizationRequest->getKey()),
            'phone' => null,
            'metadata' => [
                'anonymized' => true,
                'anonymized_at' => now()->toIso8601String(),
                'anonymization_request_id' => $anonymizationRequest->getKey(),
                'seed_source' => 'NON-PRODUCTION DEMO',
            ],
        ]);

        app(TicketLifecycleBroadcaster::class)->record(
            $ticket->fresh(),
            TicketEvent::TYPE_CREATED,
            ['seed_source' => 'demo'],
            $admin,
            TicketEvent::VISIBILITY_INTERNAL,
            false
        );

        app(TicketMergeService::class)->merge([
            'primary_ticket_id' => $ticket->getKey(),
            'secondary_ticket_id' => $duplicateTicket->getKey(),
            'correlation_id' => (string) Str::uuid(),
        ], $admin, (string) Str::uuid());

        TicketDeletionRequest::create([
            'tenant_id' => $tenant->id,
            'brand_id' => $brand->id,
            'ticket_id' => $ticket->getKey(),
            'requested_by' => $admin->getKey(),
            'status' => TicketDeletionRequest::STATUS_PENDING,
            'reason' => 'NON-PRODUCTION demo deletion request.',
            'correlation_id' => (string) Str::uuid(),
        ]);
    }
}
