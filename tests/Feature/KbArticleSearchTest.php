<?php

use App\Jobs\SyncKbArticleSearchDocument;
use App\Models\Brand;
use App\Models\KbCategory;
use App\Models\Tenant;
use App\Models\User;
use App\Services\KbArticleService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    config()->set('scout.driver', 'collection');
    config()->set('scout.queue', false);
});

/**
 * @return array<string, string>
 */
function searchHeaders(Tenant $tenant, Brand $brand): array
{
    return [
        'X-Tenant' => $tenant->slug,
        'X-Brand' => $brand->slug,
    ];
}

test('E3-F6-I2 search endpoint returns localized article payload', function () {
    /** @var Tenant $tenant */
    $tenant = Tenant::factory()->create();
    app()->instance('currentTenant', $tenant);
    /** @var Brand $brand */
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);
    app()->instance('currentBrand', $brand);
    /** @var User $admin */
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $admin->assignRole('Admin');

    /** @var KbCategory $category */
    $category = KbCategory::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);

    /** @var KbArticleService $service */
    $service = app(KbArticleService::class);

    $service->create([
        'brand_id' => $brand->id,
        'category_id' => $category->id,
        'slug' => 'searchable-guide',
        'default_locale' => 'en',
        'translations' => [
            [
                'locale' => 'en',
                'title' => 'Searchable Guide',
                'status' => 'published',
                'content' => '<p>How to search effectively</p>',
            ],
            [
                'locale' => 'fr',
                'title' => 'Guide de recherche',
                'status' => 'published',
                'content' => '<p>Comment rechercher</p>',
            ],
        ],
    ], $admin);

    $response = actingAs($admin)
        ->withHeaders(searchHeaders($tenant, $brand))
        ->getJson('/api/v1/kb-articles/search?q=search');

    $response->assertStatus(200)
        ->assertJsonPath('data.0.slug', 'searchable-guide')
        ->assertJsonPath('data.0.translations.1.locale', 'fr')
        ->assertJsonPath('meta.correlation_id', fn ($value) => filled($value));
});

test('E3-F6-I2 search validation returns standardized error schema', function () {
    /** @var Tenant $tenant */
    $tenant = Tenant::factory()->create();
    app()->instance('currentTenant', $tenant);
    /** @var Brand $brand */
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);
    app()->instance('currentBrand', $brand);
    /** @var User $admin */
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $admin->assignRole('Admin');

    $response = actingAs($admin)
        ->withHeaders(searchHeaders($tenant, $brand))
        ->getJson('/api/v1/kb-articles/search');

    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'ERR_VALIDATION');
});

test('E3-F6-I2 search enforces RBAC permissions matrix', function () {
    $cases = [
        ['Admin', 200],
        ['Agent', 200],
        ['Viewer', 200],
        ['none', 403],
    ];

    foreach ($cases as [$role, $expectedStatus]) {
        /** @var Tenant $tenant */
        $tenant = Tenant::factory()->create();
        app()->instance('currentTenant', $tenant);
        /** @var Brand $brand */
        $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);
        app()->instance('currentBrand', $brand);
        /** @var User $user */
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);

        if ($role !== 'none') {
            $user->assignRole($role);
        }

        $response = actingAs($user)
            ->withHeaders(searchHeaders($tenant, $brand))
            ->getJson('/api/v1/kb-articles/search?q=anything');

        $response->assertStatus($expectedStatus);

        if ($expectedStatus !== 200) {
            $response->assertJsonPath('error.code', 'ERR_HTTP_403');
        }
    }
});

test('E3-F6-I2 search results are tenant isolated', function () {
    /** @var Tenant $tenantOne */
    $tenantOne = Tenant::factory()->create();
    /** @var Brand $brandOne */
    $brandOne = Brand::factory()->create(['tenant_id' => $tenantOne->id]);
    /** @var Tenant $tenantTwo */
    $tenantTwo = Tenant::factory()->create();
    /** @var Brand $brandTwo */
    $brandTwo = Brand::factory()->create(['tenant_id' => $tenantTwo->id]);

    app()->instance('currentTenant', $tenantOne);
    app()->instance('currentBrand', $brandOne);

    /** @var User $adminOne */
    $adminOne = User::factory()->create(['tenant_id' => $tenantOne->id, 'brand_id' => $brandOne->id]);
    $adminOne->assignRole('Admin');
    /** @var KbCategory $categoryOne */
    $categoryOne = KbCategory::factory()->create(['tenant_id' => $tenantOne->id, 'brand_id' => $brandOne->id]);

    $service = app(KbArticleService::class);
    $service->create([
        'brand_id' => $brandOne->id,
        'category_id' => $categoryOne->id,
        'slug' => 'tenant-one-article',
        'default_locale' => 'en',
        'translations' => [
            ['locale' => 'en', 'title' => 'Shared Phrase', 'status' => 'published', 'content' => 'Tenant one body'],
        ],
    ], $adminOne);

    app()->instance('currentTenant', $tenantTwo);
    app()->instance('currentBrand', $brandTwo);

    /** @var User $adminTwo */
    $adminTwo = User::factory()->create(['tenant_id' => $tenantTwo->id, 'brand_id' => $brandTwo->id]);
    $adminTwo->assignRole('Admin');
    /** @var KbCategory $categoryTwo */
    $categoryTwo = KbCategory::factory()->create(['tenant_id' => $tenantTwo->id, 'brand_id' => $brandTwo->id]);

    $service->create([
        'brand_id' => $brandTwo->id,
        'category_id' => $categoryTwo->id,
        'slug' => 'tenant-two-article',
        'default_locale' => 'en',
        'translations' => [
            ['locale' => 'en', 'title' => 'Shared Phrase', 'status' => 'published', 'content' => 'Tenant two body'],
        ],
    ], $adminTwo);

    $response = actingAs($adminTwo)
        ->withHeaders(searchHeaders($tenantTwo, $brandTwo))
        ->getJson('/api/v1/kb-articles/search?q=Shared');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'tenant-two-article');
});

test('E3-F6-I2 search sync job dispatches and removes articles from the index', function () {
    /** @var Tenant $tenant */
    $tenant = Tenant::factory()->create();
    app()->instance('currentTenant', $tenant);
    /** @var Brand $brand */
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);
    app()->instance('currentBrand', $brand);
    /** @var User $admin */
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $admin->assignRole('Admin');
    /** @var KbCategory $category */
    $category = KbCategory::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);

    Queue::fake();

    /** @var KbArticleService $service */
    $service = app(KbArticleService::class);
    $article = $service->create([
        'brand_id' => $brand->id,
        'category_id' => $category->id,
        'slug' => 'sync-target',
        'default_locale' => 'en',
        'translations' => [
            ['locale' => 'en', 'title' => 'Queue Sync', 'status' => 'published', 'content' => 'Payload'],
        ],
    ], $admin);

    Queue::assertPushed(SyncKbArticleSearchDocument::class, function (SyncKbArticleSearchDocument $job) use ($article) {
        return $job->articleId === $article->getKey() && $job->remove === false;
    });

    $service->delete($article, $admin);

    Queue::assertPushed(SyncKbArticleSearchDocument::class, function (SyncKbArticleSearchDocument $job) use ($article) {
        return $job->articleId === $article->getKey() && $job->remove === true;
    });

    Log::shouldReceive('channel')->once()->with(config('logging.default'))->andReturnSelf();
    Log::shouldReceive('info')->once()->withArgs(function (string $message, array $context) use ($article) {
        return $message === 'kb_article.search.unindexed'
            && $context['article_id'] === $article->getKey()
            && $context['correlation_id'] === 'sync-test';
    });

    $job = new SyncKbArticleSearchDocument($article->getKey(), 'sync-test', true);
    $job->handle();
});
