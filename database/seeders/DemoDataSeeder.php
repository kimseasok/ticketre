<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\BroadcastConnection;
use App\Models\Company;
use App\Models\Contact;
use App\Models\ContactAnonymizationRequest;
use App\Models\KbCategory;
use App\Models\Message;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketRelationship;
use App\Models\TicketDeletionRequest;
use App\Models\TicketEvent;
use App\Models\TicketSubmission;
use App\Models\TicketWorkflow;
use App\Models\TicketWorkflowState;
use App\Models\TicketWorkflowTransition;
use App\Models\User;
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

        $company = Company::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Demo Company',
        ]);

        $contact = Contact::factory()->for($company)->create([
            'tenant_id' => $tenant->id,
            'name' => 'Demo Contact',
            'email' => 'contact@example.com',
        ]);

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
        $contactService = app(ContactService::class);
        $ticketService->update($ticket, ['priority' => 'high', 'workflow_state' => 'triage'], $admin);
        $contactService->update($contact, ['phone' => '+15550000000'], $admin);

        $gdprContact = Contact::create([
            'tenant_id' => $tenant->id,
            'company_id' => $company->getKey(),
            'name' => 'GDPR Demo Contact',
            'email' => 'gdpr-demo@example.com',
            'metadata' => [],
        ]);

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
