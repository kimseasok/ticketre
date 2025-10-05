<?php

namespace App\Providers;

use App\Models\Contact;
use App\Models\KbArticle;
use App\Models\Ticket;
use App\Policies\ContactPolicy;
use App\Policies\KbArticlePolicy;
use App\Policies\TicketPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Ticket::class => TicketPolicy::class,
        Contact::class => ContactPolicy::class,
        KbArticle::class => KbArticlePolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        Gate::before(fn ($user, $ability) => $user->hasRole('SuperAdmin') ? true : null);
    }
}
