<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchKbArticlesRequest;
use App\Http\Requests\StoreKbArticleRequest;
use App\Http\Requests\UpdateKbArticleRequest;
use App\Http\Resources\KbArticleResource;
use App\Http\Resources\KbArticleSearchResource;
use App\Models\KbArticle;
use App\Services\KbArticleService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class KbArticleController extends Controller
{
    public function __construct(private readonly KbArticleService $service)
    {
    }

    public function search(SearchKbArticlesRequest $request): AnonymousResourceCollection
    {
        $this->authorizeForRequest($request, 'viewAny', KbArticle::class);

        $validated = $request->validated();
        $limit = $validated['limit'] ?? 15;
        $tenantId = $request->user()->tenant_id;
        $brand = app()->bound('currentBrand') && app('currentBrand') ? app('currentBrand') : null;
        $brandId = $brand?->getKey() ?? $request->user()->brand_id;

        $builder = KbArticle::search($validated['q'])
            ->take($limit)
            ->orderByDesc('updated_at')
            ->where('tenant_id', $tenantId);

        if ($brandId) {
            $builder->where('brand_id', $brandId);
        }

        if (! empty($validated['category_id'])) {
            $builder->where('category_id', (int) $validated['category_id']);
        }

        if (! empty($validated['locale'])) {
            $builder->where('locales', $validated['locale']);
        }

        if (! empty($validated['status'])) {
            $builder->where('status', $validated['status']);
        }

        $startedAt = microtime(true);

        $articles = $builder->get()->load(['translations', 'category', 'author']);

        $correlationId = $request->header('X-Correlation-ID') ?? (string) Str::uuid();

        Log::channel(config('logging.default'))->info('kb_article.search.performed', [
            'tenant_id' => $tenantId,
            'brand_id' => $brandId,
            'category_id' => $validated['category_id'] ?? null,
            'locale' => $validated['locale'] ?? null,
            'status' => $validated['status'] ?? null,
            'limit' => $limit,
            'result_count' => $articles->count(),
            'duration_ms' => round((microtime(true) - $startedAt) * 1000, 2),
            'correlation_id' => $correlationId,
            'query_digest' => hash('sha256', $validated['q']),
        ]);

        return KbArticleSearchResource::collection($articles)
            ->additional([
                'meta' => [
                    'correlation_id' => $correlationId,
                ],
            ]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorizeForRequest($request, 'viewAny', KbArticle::class);

        $this->validateQuery($request);

        $query = KbArticle::query()->with(['category', 'author', 'translations']);

        if ($status = $request->query('status')) {
            $query->whereHas('translations', function ($builder) use ($status, $request) {
                $builder->where('status', $status);

                if ($request->query('locale')) {
                    $builder->where('locale', $request->query('locale'));
                } else {
                    $builder->whereColumn('locale', 'kb_articles.default_locale');
                }
            });
        }

        if ($categoryId = $request->query('category_id')) {
            $query->where('category_id', $categoryId);
        }

        $articles = $query->orderByDesc('updated_at')->get();

        return KbArticleResource::collection($articles);
    }

    public function store(StoreKbArticleRequest $request): JsonResponse
    {
        $this->authorizeForRequest($request, 'create', KbArticle::class);

        $article = $this->service->create($request->validated(), $request->user());

        return KbArticleResource::make($article)
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, KbArticle $kbArticle): KbArticleResource
    {
        $this->authorizeForRequest($request, 'view', $kbArticle);

        return KbArticleResource::make($kbArticle->load(['category', 'author', 'translations']));
    }

    public function update(UpdateKbArticleRequest $request, KbArticle $kbArticle): KbArticleResource
    {
        $this->authorizeForRequest($request, 'update', $kbArticle);

        $article = $this->service->update($kbArticle, $request->validated(), $request->user());

        return KbArticleResource::make($article);
    }

    public function destroy(Request $request, KbArticle $kbArticle): JsonResponse
    {
        $this->authorizeForRequest($request, 'delete', $kbArticle);

        $this->service->delete($kbArticle, $request->user());

        return response()->json(null, 204);
    }

    protected function authorizeForRequest(Request $request, string $ability, mixed $arguments): void
    {
        $response = Gate::forUser($request->user())->inspect($ability, $arguments);

        if (! $response->allowed()) {
            $this->throwAuthorizationError($response->message() ?: 'This action is unauthorized.');
        }
    }

    protected function validateQuery(Request $request): void
    {
        $validator = Validator::make($request->query(), [
            'status' => ['nullable', 'string', 'in:draft,published,archived'],
            'locale' => ['nullable', 'string', 'max:10'],
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists('kb_categories', 'id')
                    ->where('tenant_id', $request->user()->tenant_id)
                    ->when(app()->bound('currentBrand') && app('currentBrand'), fn ($query) => $query->where('brand_id', app('currentBrand')->getKey())),
            ],
        ]);

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json([
                'error' => [
                    'code' => 'ERR_VALIDATION',
                    'message' => $validator->errors()->first() ?? 'Validation failed.',
                    'details' => $validator->errors()->toArray(),
                ],
            ], 422));
        }
    }

    protected function throwAuthorizationError(string $message): void
    {
        throw new HttpResponseException(response()->json([
            'error' => [
                'code' => 'ERR_HTTP_403',
                'message' => $message,
            ],
        ], 403));
    }
}
