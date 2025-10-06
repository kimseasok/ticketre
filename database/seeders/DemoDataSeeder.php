<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Company;
use App\Models\Contact;
use App\Models\ContactAnonymizationRequest;
use App\Models\KbArticle;
use App\Models\KbCategory;
use App\Models\Message;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketDeletionRequest;
use App\Models\TicketEvent;
use App\Models\User;
use App\Services\ContactService;
use App\Services\TenantRoleProvisioner;
use App\Services\TicketLifecycleBroadcaster;
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

        KbArticle::factory()->create([
            'tenant_id' => $tenant->id,
            'brand_id' => $brand->id,
            'category_id' => $rootCategory->id,
            'author_id' => $agent->id,
            'title' => 'Welcome to the Service Desk',
            'slug' => 'welcome',
            'content' => '<p>This is demo content. DO NOT USE IN PRODUCTION.</p>',
            'status' => 'published',
            'excerpt' => 'Orientation article for the NON-PRODUCTION demo knowledge base.',
            'metadata' => ['tags' => ['welcome', 'demo']],
        ]);

        KbArticle::factory()->create([
            'tenant_id' => $tenant->id,
            'brand_id' => $brand->id,
            'category_id' => $childCategory->id,
            'author_id' => $agent->id,
            'title' => 'Resetting Your Password',
            'slug' => 'reset-password',
            'content' => '<p>Demo reset instructions. DO NOT USE IN PRODUCTION.</p>',
            'status' => 'draft',
            'excerpt' => 'Internal draft instructions for resetting credentials.',
            'metadata' => ['tags' => ['credentials', 'internal']],
        ]);

        $ticket = Ticket::factory()->create([
            'tenant_id' => $tenant->id,
            'brand_id' => $brand->id,
            'company_id' => $company->id,
            'contact_id' => $contact->id,
            'assignee_id' => $agent->id,
            'subject' => 'Demo ticket',
            'status' => 'open',
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

        TicketDeletionRequest::create([
            'tenant_id' => $tenant->id,
            'brand_id' => $brand->id,
            'ticket_id' => $ticket->getKey(),
            'requested_by' => $admin->getKey(),
            'status' => TicketDeletionRequest::STATUS_PENDING,
            'reason' => 'NON-PRODUCTION demo deletion request.',
            'correlation_id' => (string) Str::uuid(),
        ]);

        app(TicketLifecycleBroadcaster::class)->record(
            $ticket->fresh(),
            TicketEvent::TYPE_CREATED,
            ['seed_source' => 'demo'],
            $admin,
            TicketEvent::VISIBILITY_INTERNAL,
            false
        );
    }
}
