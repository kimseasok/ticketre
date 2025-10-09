<?php

namespace App\Providers;

use App\Models\AuditLog;
use App\Models\BroadcastConnection;
use App\Models\CiQualityGate;
use App\Models\Company;
use App\Models\Contact;
use App\Models\ContactAnonymizationRequest;
use App\Models\KbArticle;
use App\Models\KbCategory;
use App\Models\Message;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\TicketMerge;
use App\Models\TicketSubmission;
use App\Models\TwoFactorCredential;
use App\Models\User;
use App\Models\TicketDeletionRequest;
use App\Models\TicketRelationship;
use App\Models\TicketWorkflow;
use App\Models\Team;
use App\Models\TeamMembership;
use App\Policies\AuditLogPolicy;
use App\Policies\BroadcastConnectionPolicy;
use App\Policies\CiQualityGatePolicy;
use App\Policies\CompanyPolicy;
use App\Policies\ContactAnonymizationRequestPolicy;
use App\Policies\ContactPolicy;
use App\Policies\KbArticlePolicy;
use App\Policies\KbCategoryPolicy;
use App\Policies\MessagePolicy;
use App\Policies\PermissionPolicy;
use App\Policies\RolePolicy;
use App\Policies\TeamMembershipPolicy;
use App\Policies\TeamPolicy;
use App\Policies\TicketDeletionRequestPolicy;
use App\Policies\TicketRelationshipPolicy;
use App\Policies\TicketEventPolicy;
use App\Policies\TicketMergePolicy;
use App\Policies\TicketPolicy;
use App\Policies\TicketSubmissionPolicy;
use App\Policies\TicketWorkflowPolicy;
use App\Policies\TwoFactorCredentialPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Ticket::class => TicketPolicy::class,
        TicketEvent::class => TicketEventPolicy::class,
        TicketMerge::class => TicketMergePolicy::class,
        Contact::class => ContactPolicy::class,
        Company::class => CompanyPolicy::class,
        ContactAnonymizationRequest::class => ContactAnonymizationRequestPolicy::class,
        TicketDeletionRequest::class => TicketDeletionRequestPolicy::class,
        TicketRelationship::class => TicketRelationshipPolicy::class,
        KbArticle::class => KbArticlePolicy::class,
        KbCategory::class => KbCategoryPolicy::class,
        Message::class => MessagePolicy::class,
        Permission::class => PermissionPolicy::class,
        AuditLog::class => AuditLogPolicy::class,
        Role::class => RolePolicy::class,
        TicketSubmission::class => TicketSubmissionPolicy::class,
        BroadcastConnection::class => BroadcastConnectionPolicy::class,
        TicketWorkflow::class => TicketWorkflowPolicy::class,
        Team::class => TeamPolicy::class,
        TeamMembership::class => TeamMembershipPolicy::class,
        TwoFactorCredential::class => TwoFactorCredentialPolicy::class,
        CiQualityGate::class => CiQualityGatePolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        Gate::before(fn (User $user, string $ability) => $user->hasRole('SuperAdmin') ? true : null);
    }
}
