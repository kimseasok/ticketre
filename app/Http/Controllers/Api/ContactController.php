<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsurePermission;
use App\Http\Requests\StoreContactRequest;
use App\Http\Requests\UpdateContactRequest;
use App\Http\Resources\ContactResource;
use App\Models\Contact;
use App\Services\ContactService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ContactController extends Controller
{
    public function __construct(private readonly ContactService $service)
    {
        $this->middleware(EnsurePermission::class.':contacts.view')->only(['index', 'show']);
        $this->middleware(EnsurePermission::class.':contacts.manage')->only(['store', 'update', 'destroy']);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorizeRequest($request, 'viewAny', Contact::class);

        $query = Contact::query()->with(['company', 'tags'])->orderBy('name');

        if ($search = $request->query('search')) {
            $like = '%'.$search.'%';
            $query->where(fn ($builder) => $builder
                ->where('name', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('phone', 'like', $like));
        }

        if ($companyId = $request->query('company_id')) {
            $query->where('company_id', $companyId);
        }

        if ($request->filled('gdpr_consent')) {
            $query->where('gdpr_consent', (bool) $request->query('gdpr_consent'));
        }

        if ($tagIds = $request->query('tag_ids')) {
            $ids = array_filter(explode(',', (string) $tagIds));
            if (! empty($ids)) {
                $query->whereHas('tags', fn ($builder) => $builder->whereIn('contact_tags.id', $ids));
            }
        }

        $contacts = $query->get();

        return ContactResource::collection($contacts);
    }

    public function store(StoreContactRequest $request): JsonResponse
    {
        $contact = $this->service->create($request->validated(), $request->user());

        return ContactResource::make($contact->load(['company', 'tags']))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Contact $contact): ContactResource
    {
        $this->authorizeRequest($request, 'view', $contact);

        return ContactResource::make($contact->load(['company', 'tags']));
    }

    public function update(UpdateContactRequest $request, Contact $contact): ContactResource
    {
        $contact = $this->service->update($contact, $request->validated(), $request->user());

        return ContactResource::make($contact->load(['company', 'tags']));
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
}
