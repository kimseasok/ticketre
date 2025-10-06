<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Company;
use App\Models\Contact;
use App\Models\KbArticle;
use App\Models\KbCategory;
use App\Models\Message;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\TicketRelationship;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

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

        $brand = Brand::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Demo Brand',
            'slug' => 'demo-brand',
            'domain' => 'brand.demo.localhost',
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

        $category = KbCategory::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Getting Started',
            'slug' => 'getting-started',
        ]);

        KbArticle::factory()->create([
            'tenant_id' => $tenant->id,
            'category_id' => $category->id,
            'title' => 'Welcome to the Service Desk',
            'slug' => 'welcome',
            'content' => '<p>This is demo content. DO NOT USE IN PRODUCTION.</p>',
            'status' => 'published',
        ]);

        app()->instance('currentTenant', $tenant);
        app()->instance('currentBrand', $brand);

        $ticket = Ticket::factory()->create([
            'tenant_id' => $tenant->id,
            'brand_id' => $brand->id,
            'company_id' => $company->id,
            'contact_id' => $contact->id,
            'assignee_id' => $agent->id,
            'subject' => 'Demo ticket',
            'status' => 'open',
        ]);

        $duplicateTicket = Ticket::factory()->create([
            'tenant_id' => $tenant->id,
            'brand_id' => $brand->id,
            'company_id' => $company->id,
            'contact_id' => $contact->id,
            'assignee_id' => $agent->id,
            'subject' => 'Demo duplicate ticket',
            'status' => 'open',
        ]);

        TicketRelationship::factory()->create([
            'tenant_id' => $tenant->id,
            'brand_id' => $brand->id,
            'primary_ticket_id' => $ticket->id,
            'related_ticket_id' => $duplicateTicket->id,
            'relationship_type' => TicketRelationship::TYPE_DUPLICATE,
            'context' => ['notes' => 'Demo data only. DO NOT USE IN PRODUCTION.'],
            'created_by_id' => $admin->id,
            'updated_by_id' => $admin->id,
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

        app(\App\Services\TicketLifecycleBroadcaster::class)->record(
            $ticket->fresh(),
            TicketEvent::TYPE_CREATED,
            ['seed_source' => 'demo'],
            $admin,
            TicketEvent::VISIBILITY_INTERNAL,
            false
        );
    }
}
