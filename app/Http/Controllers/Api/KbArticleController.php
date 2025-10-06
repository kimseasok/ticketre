<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreKbArticleRequest;
use App\Http\Requests\UpdateKbArticleRequest;
use App\Http\Resources\KbArticleResource;
use App\Models\KbArticle;
use App\Models\User;
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
        $user = $this->resolveUser($request);

        $this->authorizeForRequest($request, $user, 'viewAny', KbArticle::class);

        $this->validateQuery($request, $user);

        $query = KbArticle::query()
            ->with(['category', 'author'])
            ->where('tenant_id', $user->tenant_id);

        if ($user->brand_id !== null) {
            $query->where('brand_id', $user->brand_id);
        }

        $status = $request->query('status');
        if (is_string($status) && $status !== '') {
            $query->where('status', $status);
        }

        $locale = $request->query('locale');
        if (is_string($locale) && $locale !== '') {
            $query->where('locale', $locale);
        }

        $categoryId = $request->query('category_id');
        if (is_numeric($categoryId)) {
            $query->where('category_id', (int) $categoryId);
        }

        $articles = $query->orderByDesc('updated_at')->get();

        return KbArticleResource::collection($articles);
    }

    public function store(StoreKbArticleRequest $request): JsonResponse
    {
        $user = $this->resolveUser($request);

        $this->authorizeForRequest($request, $user, 'create', KbArticle::class);

        $article = $this->service->create($request->validated(), $user);

        return KbArticleResource::make($article)
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, KbArticle $kbArticle): KbArticleResource
    {
        $user = $this->resolveUser($request);

        $this->authorizeForRequest($request, $user, 'view', $kbArticle);

        return KbArticleResource::make($kbArticle->load(['category', 'author']));
    }

    public function update(UpdateKbArticleRequest $request, KbArticle $kbArticle): KbArticleResource
    {
        $user = $this->resolveUser($request);

        $this->authorizeForRequest($request, $user, 'update', $kbArticle);

        $article = $this->service->update($kbArticle, $request->validated(), $user);

        return KbArticleResource::make($article);
    }

    public function destroy(Request $request, KbArticle $kbArticle): JsonResponse
    {
        $user = $this->resolveUser($request);

        $this->authorizeForRequest($request, $user, 'delete', $kbArticle);

        $this->service->delete($kbArticle, $user);

        return response()->json(null, 204);
    }

    protected function authorizeForRequest(Request $request, User $user, string $ability, mixed $arguments): void
    {
        $response = Gate::forUser($user)->inspect($ability, $arguments);

        if (! $response->allowed()) {
            $this->throwAuthorizationError($response->message() ?: 'This action is unauthorized.');
        }
    }

    protected function validateQuery(Request $request, User $user): void
    {
        $validator = Validator::make($request->query(), [
            'status' => ['nullable', 'string', 'in:draft,published,archived'],
            'locale' => ['nullable', 'string', 'max:10'],
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists('kb_categories', 'id')
                    ->where('tenant_id', $user->tenant_id)
                    ->when($user->brand_id !== null, fn ($query) => $query->where('brand_id', $user->brand_id)),
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

    protected function resolveUser(Request $request): User
    {
        $user = $request->user();

        if ($user instanceof User) {
            return $user;
        }

        throw new HttpResponseException(response()->json([
            'error' => [
                'code' => 'ERR_UNAUTHENTICATED',
                'message' => 'Authentication required.',
            ],
        ], 401));
    }
}
