<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreKbArticleRequest;
use App\Http\Requests\UpdateKbArticleRequest;
use App\Http\Resources\KbArticleResource;
use App\Models\KbArticle;
use App\Services\KbArticleService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class KbArticleController extends Controller
{
    public function __construct(private readonly KbArticleService $service)
    {
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
