<?php

namespace App\Providers;

use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\ContactAnonymizationRequest;
use App\Models\KbArticle;
use App\Models\KbCategory;
use App\Models\Message;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\TicketSubmission;
use App\Models\TicketDeletionRequest;
use App\Policies\AuditLogPolicy;
use App\Policies\ContactAnonymizationRequestPolicy;
use App\Policies\ContactPolicy;
use App\Policies\KbArticlePolicy;
use App\Policies\KbCategoryPolicy;
use App\Policies\MessagePolicy;
use App\Policies\RolePolicy;
use App\Policies\TicketDeletionRequestPolicy;
use App\Policies\TicketEventPolicy;
use App\Policies\TicketPolicy;
use App\Policies\TicketSubmissionPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Ticket::class => TicketPolicy::class,
        TicketEvent::class => TicketEventPolicy::class,
        Contact::class => ContactPolicy::class,
        ContactAnonymizationRequest::class => ContactAnonymizationRequestPolicy::class,
        TicketDeletionRequest::class => TicketDeletionRequestPolicy::class,
        KbArticle::class => KbArticlePolicy::class,
        KbCategory::class => KbCategoryPolicy::class,
        Message::class => MessagePolicy::class,
        AuditLog::class => AuditLogPolicy::class,
        Role::class => RolePolicy::class,
        TicketSubmission::class => TicketSubmissionPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        Gate::before(fn ($user, $ability) => $user->hasRole('SuperAdmin') ? true : null);
    }
}
