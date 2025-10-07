<?php

use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\KbArticle;
use App\Models\KbCategory;
use App\Models\Tenant;
use App\Models\User;

it('E3-F2-I3 sanitizes malicious knowledge base HTML and records audit log', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('currentTenant', $tenant);
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);
    app()->instance('currentBrand', $brand);
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $admin->assignRole('Admin');
    $category = KbCategory::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);

    $maliciousContent = '<p>Trusted copy</p><script>alert(1)</script><img src="javascript:alert(2)" onerror="alert(3)"><a href="javascript:alert(4)" onclick="alert(5)">Read more</a>';
    $maliciousExcerpt = 'Preview<script>alert(6)</script>';

    $response = $this
        ->actingAs($admin)
        ->withHeaders([
            'X-Tenant' => $tenant->slug,
            'X-Brand' => $brand->slug,
        ])
        ->postJson('/api/v1/kb-articles', [
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'slug' => 'sanitization-check',
            'default_locale' => 'en',
            'translations' => [
                [
                    'locale' => 'en',
                    'title' => 'Security Advisory',
                    'status' => 'published',
                    'content' => $maliciousContent,
                    'excerpt' => $maliciousExcerpt,
                ],
            ],
        ]);

    $response->assertStatus(201);

    $article = KbArticle::with('translations')->find($response->json('data.id'));
    expect($article)->not->toBeNull();
    $translation = $article->translations->first();
    expect($translation->content)->toContain('Trusted copy')
        ->and($translation->content)->not->toContain('<script')
        ->and($translation->content)->not->toContain('javascript:')
        ->and($translation->content)->not->toContain('onclick')
        ->and($translation->excerpt)->toBe('Preview');

    $auditLogs = AuditLog::where('action', 'kb_article.sanitization_blocked')->orderBy('id')->get();
    expect($auditLogs)->toHaveCount(2);

    $contentAudit = $auditLogs->firstWhere('changes.field', 'content');
    expect($contentAudit)->not->toBeNull()
        ->and($contentAudit->changes['blocked_elements'])->toContain('script')
        ->and($contentAudit->changes['blocked_attributes'])->toContain('onerror')
        ->and($contentAudit->changes['blocked_protocols'])->toContain('javascript')
        ->and($contentAudit->changes['preview'])->toContain('Trusted copy');

    $excerptAudit = $auditLogs->firstWhere('changes.field', 'excerpt');
    expect($excerptAudit)->not->toBeNull()
        ->and($excerptAudit->changes['blocked_elements'])->toContain('script')
        ->and($excerptAudit->changes['preview'])->toBe('Preview');
});

it('E3-F2-I3 preserves safe HTML without generating sanitization noise', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('currentTenant', $tenant);
    $brand = Brand::factory()->create(['tenant_id' => $tenant->id]);
    app()->instance('currentBrand', $brand);
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);
    $admin->assignRole('Admin');
    $category = KbCategory::factory()->create(['tenant_id' => $tenant->id, 'brand_id' => $brand->id]);

    $payload = [
        'brand_id' => $brand->id,
        'category_id' => $category->id,
        'slug' => 'safe-article',
        'default_locale' => 'en',
        'translations' => [
            [
                'locale' => 'en',
                'title' => 'Safe Body',
                'status' => 'draft',
                'content' => '<p>Plain <strong>formatted</strong> copy.</p>',
                'excerpt' => 'Plain formatted copy.',
            ],
        ],
    ];

    $createResponse = $this
        ->actingAs($admin)
        ->withHeaders([
            'X-Tenant' => $tenant->slug,
            'X-Brand' => $brand->slug,
        ])
        ->postJson('/api/v1/kb-articles', $payload);

    $createResponse->assertStatus(201);

    expect(AuditLog::where('action', 'kb_article.sanitization_blocked')->count())->toBe(0);

    $articleId = $createResponse->json('data.id');

    $updateResponse = $this
        ->actingAs($admin)
        ->withHeaders([
            'X-Tenant' => $tenant->slug,
            'X-Brand' => $brand->slug,
        ])
        ->patchJson('/api/v1/kb-articles/'.$articleId, [
            'translations' => [
                [
                    'locale' => 'en',
                    'title' => 'Safe Body',
                    'status' => 'published',
                    'content' => '<p>Plain <strong>formatted</strong> copy.</p>',
                    'excerpt' => 'Plain formatted copy.',
                ],
            ],
        ]);

    $updateResponse->assertStatus(200);

    expect(AuditLog::where('action', 'kb_article.sanitization_blocked')->count())->toBe(0);
});
