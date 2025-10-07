<?php

$kbArticlesIndex = env('MEILISEARCH_KB_ARTICLES_INDEX', 'kb_articles');

return [
    'driver' => env('SCOUT_DRIVER', 'null'),
    'queue' => env('SCOUT_QUEUE', true),
    'after_commit' => false,
    'prefix' => env('SCOUT_PREFIX', ''),
    'meilisearch' => [
        'host' => env('MEILISEARCH_HOST', 'http://localhost:7700'),
        'key' => env('MEILISEARCH_KEY', null),
        'index_settings' => [
            $kbArticlesIndex => [
                'filterableAttributes' => [
                    'tenant_id',
                    'brand_id',
                    'category_id',
                    'locale',
                    'locales',
                    'status',
                ],
                'sortableAttributes' => [
                    'updated_at',
                    'created_at',
                ],
                'searchableAttributes' => [
                    'title',
                    'content',
                    'translations.title',
                    'translations.excerpt',
                ],
                'displayedAttributes' => [
                    'id',
                    'slug',
                    'tenant_id',
                    'brand_id',
                    'category_id',
                    'default_locale',
                    'locale',
                    'status',
                    'title',
                    'excerpt',
                    'locales',
                    'published_locales',
                    'translations',
                    'updated_at',
                    'created_at',
                ],
            ],
        ],
    ],
];
