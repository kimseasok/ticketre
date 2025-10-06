<?php

namespace App\Providers;

use App\Models\Contact;
use App\Models\KbArticle;
use App\Models\KbCategory;
use App\Models\Message;
use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Policies\ContactPolicy;
use App\Policies\KbArticlePolicy;
use App\Policies\KbCategoryPolicy;
use App\Policies\MessagePolicy;
use App\Policies\TicketEventPolicy;
use App\Policies\TicketPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Ticket::class => TicketPolicy::class,
        TicketEvent::class => TicketEventPolicy::class,
        Contact::class => ContactPolicy::class,
        KbArticle::class => KbArticlePolicy::class,
        KbCategory::class => KbCategoryPolicy::class,
        Message::class => MessagePolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        Gate::before(fn ($user, $ability) => $user->hasRole('SuperAdmin') ? true : null);
    }
}
