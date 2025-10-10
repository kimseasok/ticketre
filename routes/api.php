<?php

use App\Http\Controllers\Api\AnonymizationPolicyController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\BrandDomainController;
use App\Http\Controllers\Api\BroadcastAuthController;
use App\Http\Controllers\Api\BroadcastConnectionController;
use App\Http\Controllers\Api\CiQualityGateController;
use App\Http\Controllers\Api\HorizonDeploymentController;
use App\Http\Controllers\Api\ContactAnonymizationRequestController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\HealthcheckController;
use App\Http\Controllers\Api\KbArticleController;
use App\Http\Controllers\Api\KbCategoryController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\ObservabilityMetricsController;
use App\Http\Controllers\Api\ObservabilityPipelineController;
use App\Http\Controllers\Api\ObservabilityStackController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\PortalTicketSubmissionController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\RedisConfigurationController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\TicketMergeController;
use App\Http\Controllers\Api\TicketWorkflowController;
use App\Http\Controllers\Api\TicketDeletionRequestController;
use App\Http\Controllers\Api\TicketRelationshipController;
use App\Http\Controllers\Api\TicketSubmissionController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\TeamMembershipController;
use App\Http\Controllers\Api\TwoFactorController;
use Illuminate\Support\Facades\Route;

Route::get('/v1/health', [HealthcheckController::class, 'show'])->name('api.health');

Route::middleware(['tenant', 'ability:portal.submit,allow-guest'])->prefix('v1/portal')->name('api.portal.')->group(function () {
    Route::post('tickets', [PortalTicketSubmissionController::class, 'store'])->name('tickets.store');
});

Route::post('/v1/broadcasting/auth', BroadcastAuthController::class)
    ->middleware([
        'api',
        'auth:api,web',
        'tenant',
        \Illuminate\Session\Middleware\StartSession::class,
        \App\Http\Middleware\EnsureTwoFactorEnrolled::class,
        'ability:platform.access',
    ])
    ->name('api.broadcasting.auth');

Route::middleware([
    'api',
    'auth',
    'tenant',
    \Illuminate\Session\Middleware\StartSession::class,
    \App\Http\Middleware\EnsureTwoFactorEnrolled::class,
    'ability:platform.access',
])->prefix('v1')->name('api.')->group(function () {
    Route::scopeBindings()->group(function () {
    Route::apiResource('tickets', TicketController::class)->only(['index', 'store', 'show', 'update']);

        Route::get('tickets/{ticket}/events', [TicketController::class, 'events'])->name('tickets.events.index');
        Route::post('tickets/{ticket}/events', [TicketController::class, 'storeEvent'])->name('tickets.events.store');

        Route::apiResource('tickets.messages', MessageController::class)->parameters([
            'tickets' => 'ticket',
            'messages' => 'message',
        ]);
    });

    Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');

    Route::apiResource('contact-anonymization-requests', ContactAnonymizationRequestController::class)
        ->only(['index', 'store', 'show']);

    Route::apiResource('brands', BrandController::class)->except(['create', 'edit']);
    Route::apiResource('brand-domains', BrandDomainController::class)
        ->parameters(['brand-domains' => 'brand_domain'])
        ->except(['create', 'edit']);
    Route::post('brand-domains/{brand_domain}/verify', [BrandDomainController::class, 'verify'])
        ->name('brand-domains.verify');

    Route::apiResource('companies', CompanyController::class)->except(['create', 'edit']);
    Route::apiResource('contacts', ContactController::class)->except(['create', 'edit']);

    Route::apiResource('anonymization-policies', AnonymizationPolicyController::class)
        ->except(['create', 'edit']);

    Route::apiResource('ticket-deletion-requests', TicketDeletionRequestController::class)
        ->only(['index', 'store', 'show']);

    Route::post('ticket-deletion-requests/{ticketDeletionRequest}/approve', [
        TicketDeletionRequestController::class,
        'approve',
    ])->name('ticket-deletion-requests.approve');

    Route::post('ticket-deletion-requests/{ticketDeletionRequest}/cancel', [
        TicketDeletionRequestController::class,
        'cancel',
    ])->name('ticket-deletion-requests.cancel');

    Route::apiResource('ticket-merges', TicketMergeController::class)->only(['index', 'store', 'show']);
    Route::apiResource('ticket-relationships', TicketRelationshipController::class)->except(['create', 'edit']);
    Route::apiResource('ticket-workflows', TicketWorkflowController::class)->except(['create', 'edit']);
    Route::apiResource('ci-quality-gates', CiQualityGateController::class)->except(['create', 'edit']);
    Route::get('horizon-deployments/health', [HorizonDeploymentController::class, 'health'])->name('horizon-deployments.health');
    Route::get('horizon-deployments/{horizon_deployment}/health', [HorizonDeploymentController::class, 'showHealth'])->name('horizon-deployments.show-health');
    Route::apiResource('horizon-deployments', HorizonDeploymentController::class)->except(['create', 'edit']);
    Route::get('observability-pipelines/metrics', ObservabilityMetricsController::class)
        ->name('observability-pipelines.metrics');
    Route::apiResource('observability-pipelines', ObservabilityPipelineController::class)->except(['create', 'edit']);
    Route::apiResource('observability-stacks', ObservabilityStackController::class)->except(['create', 'edit']);
    Route::apiResource('teams', TeamController::class)->except(['create', 'edit']);
    Route::apiResource('teams.memberships', TeamMembershipController::class)
        ->parameters(['memberships' => 'teamMembership'])
        ->except(['create', 'edit']);

    Route::apiResource('kb-categories', KbCategoryController::class)->except(['create', 'edit']);
    Route::get('kb-articles/search', [KbArticleController::class, 'search'])->name('kb-articles.search');
    Route::apiResource('kb-articles', KbArticleController::class)->except(['create', 'edit']);
    Route::apiResource('roles', RoleController::class)->except(['create', 'edit']);
    Route::apiResource('redis-configurations', RedisConfigurationController::class)->except(['create', 'edit']);
    Route::apiResource('permissions', \App\Http\Controllers\Api\PermissionController::class)->except(['create', 'edit']);
    Route::apiResource('ticket-submissions', TicketSubmissionController::class)->only(['index', 'show']);
    Route::apiResource('broadcast-connections', BroadcastConnectionController::class)->except(['create', 'edit']);

    Route::prefix('two-factor')->name('two-factor.')->group(function (): void {
        Route::get('/', [TwoFactorController::class, 'show'])->name('show');
        Route::post('enroll', [TwoFactorController::class, 'store'])->name('enroll');
        Route::post('confirm', [TwoFactorController::class, 'confirm'])->name('confirm');
        Route::post('recovery-codes', [TwoFactorController::class, 'regenerate'])->name('recovery-codes.regenerate');
        Route::post('challenge', [TwoFactorController::class, 'challenge'])->name('challenge');
    });
});
