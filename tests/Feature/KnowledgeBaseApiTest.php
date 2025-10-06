<?php

use App\Models\Brand;
use App\Models\KbArticle;
use App\Models\KbCategory;
use App\Models\Tenant;
use App\Models\User;
use App\Services\KbArticleService;
use Illuminate\Support\Facades\DB;

function kbHeaders(Tenant $tenant, Brand $brand): array
{
    return [
        'X-Tenant' => $tenant->slug,
        'X-Brand' => $brand->slug,
    ];
}

test('E3-F1-I2 admin can manage knowledge base categories and articles', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('currentTenant', $tenant);
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);
    app()->instance('currentBrand', $brand);
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $admin->assignRole('Admin');

    $categoryResponse = $this
        ->actingAs($admin)
        ->withHeaders(kbHeaders($tenant, $brand))
        ->postJson('/api/v1/kb-categories', [
            'brand_id' => $brand->id,
            'name' => 'Platform Overview',
            'slug' => 'platform-overview',
            'order' => 1,
        ]);

    $categoryResponse->assertStatus(201)
        ->assertJsonPath('data.name', 'Platform Overview');

    $categoryId = $categoryResponse->json('data.id');

    $childResponse = $this
        ->actingAs($admin)
        ->withHeaders(kbHeaders($tenant, $brand))
        ->postJson('/api/v1/kb-categories', [
            'brand_id' => $brand->id,
            'name' => 'Release Notes',
            'slug' => 'release-notes',
            'parent_id' => $categoryId,
            'order' => 2,
        ]);

    $childResponse->assertStatus(201)
        ->assertJsonPath('data.parent_id', $categoryId);

    $childId = $childResponse->json('data.id');

    expect(DB::table('kb_category_closure')->where('ancestor_id', $categoryId)->where('descendant_id', $childId)->where('depth', 1)->exists())->toBeTrue();

    $articleResponse = $this
        ->actingAs($admin)
        ->withHeaders(kbHeaders($tenant, $brand))
        ->postJson('/api/v1/kb-articles', [
            'brand_id' => $brand->id,
            'category_id' => $categoryId,
            'slug' => 'onboarding-checklist',
            'default_locale' => 'en',
            'translations' => [
                [
                    'locale' => 'en',
                    'title' => 'Onboarding Checklist',
                    'status' => 'published',
                    'content' => '<p>Checklist</p>',
                    'excerpt' => 'NON-PRODUCTION onboarding guide.',
                    'metadata' => ['tags' => ['onboarding']],
                ],
            ],
        ]);

    $articleResponse->assertStatus(201)
        ->assertJsonPath('data.status', 'published')
        ->assertJsonPath('data.translations.0.locale', 'en');

    $articleId = $articleResponse->json('data.id');

    $article = KbArticle::find($articleId);
    expect($article)->not->toBeNull()
        ->and($article->defaultTranslation?->status)->toBe('published')
        ->and($article->defaultTranslation?->published_at)->not->toBeNull();
});

test('E3-F1-I2 validation errors return standardized schema', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('currentTenant', $tenant);
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);
    app()->instance('currentBrand', $brand);
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $admin->assignRole('Admin');

    $response = $this
        ->actingAs($admin)
        ->withHeaders(kbHeaders($tenant, $brand))
        ->postJson('/api/v1/kb-categories', [
            'brand_id' => $brand->id,
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'ERR_VALIDATION');

    $category = KbCategory::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);

    $articleResponse = $this
        ->actingAs($admin)
        ->withHeaders(kbHeaders($tenant, $brand))
        ->postJson('/api/v1/kb-articles', [
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'slug' => 'missing-title',
            'translations' => [
                [
                    'locale' => 'en',
                    'status' => 'invalid-status',
                ],
            ],
        ]);

    $articleResponse->assertStatus(422)
        ->assertJsonPath('error.code', 'ERR_VALIDATION');
});

test('E3-F1-I2 knowledge base management enforces RBAC', function (string $role, int $expectedStatus) {
    $tenant = Tenant::factory()->create();
    app()->instance('currentTenant', $tenant);
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);
    app()->instance('currentBrand', $brand);
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $user->assignRole($role);

    $response = $this
        ->actingAs($user)
        ->withHeaders(kbHeaders($tenant, $brand))
        ->postJson('/api/v1/kb-categories', [
            'brand_id' => $brand->id,
            'name' => 'Policy Check',
            'slug' => 'policy-check-'.$role,
        ]);

    $response->assertStatus($expectedStatus);

    if ($expectedStatus !== 201) {
        $response->assertJsonPath('error.code', 'ERR_HTTP_403');
        $category = KbCategory::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    } else {
        $category = KbCategory::find($response->json('data.id'));
    }

    $articleResponse = $this
        ->actingAs($user)
        ->withHeaders(kbHeaders($tenant, $brand))
        ->postJson('/api/v1/kb-articles', [
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'slug' => 'policy-check-article-'.$role,
            'translations' => [
                ['locale' => 'en', 'title' => 'Policy Article', 'status' => 'draft', 'content' => '...'],
            ],
        ]);

    $articleResponse->assertStatus($expectedStatus);

    if ($expectedStatus !== 201) {
        $articleResponse->assertJsonPath('error.code', 'ERR_HTTP_403');
    }
})->with([
    ['Admin', 201],
    ['Agent', 201],
    ['Viewer', 403],
]);

test('E3-F1-I2 tenant isolation prevents cross-tenant access to knowledge base data', function () {
    $tenantOne = Tenant::factory()->create();
    app()->instance('currentTenant', $tenantOne);
    $brandOne = Brand::factory()->create(['tenant_id' => $tenantOne->id]);
    app()->instance('currentBrand', $brandOne);
    $tenantTwo = Tenant::factory()->create();
    app()->instance('currentTenant', $tenantTwo);
    $brandTwo = Brand::factory()->create(['tenant_id' => $tenantTwo->id]);
    app()->instance('currentBrand', $brandTwo);

    $adminOne = User::factory()->create(['tenant_id' => $tenantOne->id, 'brand_id' => $brandOne->id]);
    $adminOne->assignRole('Admin');

    $category = KbCategory::factory()->create([
        'tenant_id' => $tenantOne->id,
        'brand_id' => $brandOne->id,
        'name' => 'Internal',
        'slug' => 'internal',
    ]);

    $adminTwo = User::factory()->create(['tenant_id' => $tenantTwo->id, 'brand_id' => $brandTwo->id]);
    $adminTwo->assignRole('Admin');

    $response = $this
        ->actingAs($adminTwo)
        ->withHeaders(kbHeaders($tenantTwo, $brandTwo))
        ->getJson('/api/v1/kb-categories/'.$category->getKey());

    $response->assertStatus(404);
});

test('E3-F1-I2 article index respects filters and returns api resource payload', function () {
    $tenant = Tenant::factory()->create();
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $admin->assignRole('Admin');

    $category = KbCategory::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);

    $service = app(KbArticleService::class);

    $service->create([
        'brand_id' => $brand->id,
        'category_id' => $category->id,
        'slug' => 'published-article-one',
        'default_locale' => 'en',
        'author_id' => $admin->id,
        'translations' => [
            [
                'locale' => 'en',
                'title' => 'Published Article One',
                'status' => 'published',
                'content' => '<p>Published content</p>',
            ],
        ],
    ], $admin);

    $service->create([
        'brand_id' => $brand->id,
        'category_id' => $category->id,
        'slug' => 'published-article-two',
        'default_locale' => 'en',
        'author_id' => $admin->id,
        'translations' => [
            [
                'locale' => 'en',
                'title' => 'Published Article Two',
                'status' => 'published',
                'content' => '<p>Another published content</p>',
            ],
        ],
    ], $admin);

    $service->create([
        'brand_id' => $brand->id,
        'category_id' => $category->id,
        'slug' => 'draft-article',
        'default_locale' => 'en',
        'author_id' => $admin->id,
        'translations' => [
            [
                'locale' => 'en',
                'title' => 'Draft Article',
                'status' => 'draft',
                'content' => '<p>Draft content</p>',
            ],
        ],
    ], $admin);

    $response = $this
        ->actingAs($admin)
        ->withHeaders(kbHeaders($tenant, $brand))
        ->getJson('/api/v1/kb-articles?status=published');

    $response->assertStatus(200);
    expect(collect($response->json('data'))->pluck('status')->unique()->all())->toEqual(['published']);
});

test('E3-F5-I2 admin can manage multilingual translations on knowledge base articles', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('currentTenant', $tenant);
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);
    app()->instance('currentBrand', $brand);
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $admin->assignRole('Admin');
    $category = KbCategory::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);

    $createResponse = $this
        ->actingAs($admin)
        ->withHeaders(kbHeaders($tenant, $brand))
        ->postJson('/api/v1/kb-articles', [
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'slug' => 'multilingual-article',
            'default_locale' => 'en',
            'translations' => [
                [
                    'locale' => 'en',
                    'title' => 'English Title',
                    'status' => 'published',
                    'content' => '<p>English body</p>',
                ],
                [
                    'locale' => 'es',
                    'title' => 'Título Español',
                    'status' => 'draft',
                    'content' => '<p>Cuerpo español</p>',
                ],
            ],
        ]);

    $createResponse->assertStatus(201);

    $articleId = $createResponse->json('data.id');

    $updateResponse = $this
        ->actingAs($admin)
        ->withHeaders(kbHeaders($tenant, $brand))
        ->patchJson('/api/v1/kb-articles/'.$articleId, [
            'default_locale' => 'en',
            'translations' => [
                [
                    'locale' => 'en',
                    'title' => 'English Title Updated',
                    'status' => 'published',
                    'content' => '<p>Updated content</p>',
                ],
                [
                    'locale' => 'es',
                    'delete' => true,
                ],
                [
                    'locale' => 'fr',
                    'title' => 'Titre Français',
                    'status' => 'draft',
                    'content' => '<p>Contenu français</p>',
                ],
            ],
        ]);

    $updateResponse->assertOk()
        ->assertJsonPath('data.translations.0.locale', 'en')
        ->assertJsonFragment(['locale' => 'fr']);

    $article = KbArticle::with(['translations' => fn ($query) => $query->withTrashed()])->find($articleId);
    expect($article)->not->toBeNull()
        ->and($article->translations->whereNull('deleted_at')->pluck('locale')->sort()->values()->all())->toEqual(['en', 'fr'])
        ->and($article->translations()->withTrashed()->where('locale', 'es')->first()?->trashed())->toBeTrue();
});

test('E3-F5-I2 validation enforces unique locales and default locale membership', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('currentTenant', $tenant);
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);
    app()->instance('currentBrand', $brand);
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $admin->assignRole('Admin');
    $category = KbCategory::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);

    $response = $this
        ->actingAs($admin)
        ->withHeaders(kbHeaders($tenant, $brand))
        ->postJson('/api/v1/kb-articles', [
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'slug' => 'duplicate-locales',
            'default_locale' => 'fr',
            'translations' => [
                ['locale' => 'en', 'title' => 'English', 'status' => 'draft', 'content' => '...'],
                ['locale' => 'en', 'title' => 'English Duplicate', 'status' => 'draft', 'content' => '...'],
            ],
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'ERR_VALIDATION');
});

test('E3-F5-I2 viewer cannot create multilingual knowledge base articles', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('currentTenant', $tenant);
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);
    app()->instance('currentBrand', $brand);
    $viewer = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $viewer->assignRole('Viewer');
    $category = KbCategory::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);

    $response = $this
        ->actingAs($viewer)
        ->withHeaders(kbHeaders($tenant, $brand))
        ->postJson('/api/v1/kb-articles', [
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'slug' => 'forbidden-article',
            'translations' => [
                ['locale' => 'en', 'title' => 'Forbidden', 'status' => 'draft', 'content' => '...'],
            ],
        ]);

    $response->assertStatus(403)
        ->assertJsonPath('error.code', 'ERR_HTTP_403');
});

test('E3-F5-I2 tenant isolation blocks cross-tenant article access', function () {
    $tenantOne = Tenant::factory()->create();
    app()->instance('currentTenant', $tenantOne);
    $brandOne = Brand::factory()->create(['tenant_id' => $tenantOne->id]);
    app()->instance('currentBrand', $brandOne);
    $adminOne = User::factory()->create(['tenant_id' => $tenantOne->id, 'brand_id' => $brandOne->id]);
    $adminOne->assignRole('Admin');
    $categoryOne = KbCategory::factory()->create(['tenant_id' => $tenantOne->id, 'brand_id' => $brandOne->id]);

    $articleResponse = $this
        ->actingAs($adminOne)
        ->withHeaders(kbHeaders($tenantOne, $brandOne))
        ->postJson('/api/v1/kb-articles', [
            'brand_id' => $brandOne->id,
            'category_id' => $categoryOne->id,
            'slug' => 'isolated-article',
            'translations' => [
                ['locale' => 'en', 'title' => 'Isolated', 'status' => 'draft', 'content' => '...'],
            ],
        ]);

    $articleResponse->assertStatus(201);

    $tenantTwo = Tenant::factory()->create();
    app()->instance('currentTenant', $tenantTwo);
    $brandTwo = Brand::factory()->create(['tenant_id' => $tenantTwo->id]);
    app()->instance('currentBrand', $brandTwo);
    $adminTwo = User::factory()->create(['tenant_id' => $tenantTwo->id, 'brand_id' => $brandTwo->id]);
    $adminTwo->assignRole('Admin');

    $response = $this
        ->actingAs($adminTwo)
        ->withHeaders(kbHeaders($tenantTwo, $brandTwo))
        ->getJson('/api/v1/kb-articles/'.$articleResponse->json('data.id'));

    $response->assertStatus(404);
});

test('E3-F5-I2 article show falls back to default locale when translation missing', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('currentTenant', $tenant);
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);
    app()->instance('currentBrand', $brand);
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $admin->assignRole('Admin');
    $category = KbCategory::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);

    $service = app(KbArticleService::class);

    $article = $service->create([
        'brand_id' => $brand->id,
        'category_id' => $category->id,
        'slug' => 'fallback-article',
        'default_locale' => 'en',
        'author_id' => $admin->id,
        'translations' => [
            [
                'locale' => 'en',
                'title' => 'Fallback Title',
                'status' => 'published',
                'content' => '<p>Fallback content</p>',
            ],
        ],
    ], $admin);

    $response = $this
        ->actingAs($admin)
        ->withHeaders(kbHeaders($tenant, $brand))
        ->getJson('/api/v1/kb-articles/'.$article->getKey().'?locale=fr');

    $response->assertOk()
        ->assertJsonPath('data.locale', 'en')
        ->assertJsonPath('data.title', 'Fallback Title');
});
