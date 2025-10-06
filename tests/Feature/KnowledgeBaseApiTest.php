<?php

use App\Models\Brand;
use App\Models\KbArticle;
use App\Models\KbCategory;
use App\Models\Tenant;
use App\Models\User;
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
            'title' => 'Onboarding Checklist',
            'slug' => 'onboarding-checklist',
            'content' => '<p>Checklist</p>',
            'status' => 'published',
            'locale' => 'en',
            'excerpt' => 'NON-PRODUCTION onboarding guide.',
            'metadata' => ['tags' => ['onboarding']],
        ]);

    $articleResponse->assertStatus(201)
        ->assertJsonPath('data.status', 'published');

    $articleId = $articleResponse->json('data.id');

    $article = KbArticle::find($articleId);
    expect($article)->not->toBeNull()
        ->and($article->published_at)->not->toBeNull();
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
            'status' => 'invalid-status',
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

    KbArticle::factory()->count(2)->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'category_id' => $category->id,
        'status' => 'published',
    ]);

    KbArticle::factory()->create([
        'tenant_id' => $tenant->id,
        'brand_id' => $brand->id,
        'category_id' => $category->id,
        'status' => 'draft',
    ]);

    $response = $this
        ->actingAs($admin)
        ->withHeaders(kbHeaders($tenant, $brand))
        ->getJson('/api/v1/kb-articles?status=published');

    $response->assertStatus(200);
    expect(collect($response->json('data'))->pluck('status')->unique()->all())->toEqual(['published']);
});
