<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContactRequest;
use App\Http\Requests\UpdateContactRequest;
use App\Http\Resources\ContactResource as ContactApiResource;
use App\Models\Contact;
use App\Services\ContactService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class ContactController extends Controller
{
    public function __construct(private readonly ContactService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorizeRequest($request, 'viewAny', Contact::class);

        $query = Contact::query()->with(['company', 'tags']);

        if ($search = $request->query('search')) {
            $like = '%'.$search.'%';
            $query->where(function ($builder) use ($like) {
                $builder->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like);
            });
        }

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->integer('company_id'));
        }

        if ($request->filled('marketing_opt_in')) {
            $query->where('gdpr_marketing_opt_in', filter_var($request->input('marketing_opt_in'), FILTER_VALIDATE_BOOLEAN));
        }

        $tags = $this->extractTagFilters($request);

        if (! empty($tags)) {
            $query->whereHas('tags', fn ($tagQuery) => $tagQuery->whereIn('slug', $tags));
        }

        $query->orderBy('name');

        $contacts = $query->paginate($request->integer('per_page', 25))->withQueryString();

        return ContactApiResource::collection($contacts);
    }

    public function store(StoreContactRequest $request): JsonResponse
    {
        $contact = $this->service->create($request->validated(), $request->user());

        return ContactApiResource::make($contact)
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Contact $contact): ContactApiResource
    {
        $this->authorizeRequest($request, 'view', $contact);

        $contact->load(['company', 'tags']);

        return ContactApiResource::make($contact);
    }

    public function update(UpdateContactRequest $request, Contact $contact): ContactApiResource
    {
        $contact = $this->service->update($contact, $request->validated(), $request->user());

        return ContactApiResource::make($contact);
    }

    public function destroy(Request $request, Contact $contact): JsonResponse
    {
        $this->authorizeRequest($request, 'delete', $contact);

        $this->service->delete($contact, $request->user());

        return response()->json(null, 204);
    }

    protected function authorizeRequest(Request $request, string $ability, mixed $arguments): void
    {
        $user = $request->user();

        if (! $user) {
            throw new HttpResponseException(response()->json([
                'error' => [
                    'code' => 'ERR_HTTP_401',
                    'message' => 'Authentication required.',
                ],
            ], 401));
        }

        $response = Gate::forUser($user)->inspect($ability, $arguments);

        if (! $response->allowed()) {
            throw new HttpResponseException(response()->json([
                'error' => [
                    'code' => 'ERR_HTTP_403',
                    'message' => $response->message() ?: 'This action is unauthorized.',
                ],
            ], 403));
        }
    }

    /**
     * @return array<int, string>
     */
    protected function extractTagFilters(Request $request): array
    {
        $tags = $request->query('tags');

        if (is_string($tags)) {
            $tags = explode(',', $tags);
        }

        if (! is_array($tags)) {
            return [];
        }

        /** @var array<int, string> $slugs */
        $slugs = collect($tags)
            ->map(fn ($tag) => trim((string) $tag))
            ->filter()
            ->map(fn ($tag) => Str::slug($tag))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $slugs;
    }
}
