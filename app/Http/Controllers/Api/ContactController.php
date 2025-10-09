<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContactRequest;
use App\Http\Requests\UpdateContactRequest;
use App\Http\Resources\ContactResource;
use App\Models\Contact;
use App\Services\ContactService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ContactController extends Controller
{
    public function __construct(private readonly ContactService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Contact::class);

        $query = Contact::query()->with(['company']);

        if ($search = $request->query('search')) {
            $like = '%' . $search . '%';
            $query->where(fn ($builder) => $builder
                ->where('name', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('phone', 'like', $like));
        }

        if ($companyId = $request->query('company_id')) {
            $query->where('company_id', $companyId);
        }

        if ($tag = $request->query('tag')) {
            $query->whereJsonContains('tags', $tag);
        }

        $contacts = $query->orderByDesc('updated_at')->paginate();

        return ContactResource::collection($contacts);
    }

    public function store(StoreContactRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $contact = $this->service->create($request->validated(), $user, $request->header('X-Correlation-ID'));

        return ContactResource::make($contact->load('company'))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Contact $contact): ContactResource
    {
        $this->authorize('view', $contact);

        return ContactResource::make($contact->load('company'));
    }

    public function update(UpdateContactRequest $request, Contact $contact): ContactResource
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $updated = $this->service->update($contact, $request->validated(), $user, $request->header('X-Correlation-ID'));

        return ContactResource::make($updated->load('company'));
    }

    public function destroy(Request $request, Contact $contact): JsonResponse
    {
        $this->authorize('delete', $contact);

        /** @var \App\Models\User $user */
        $user = $request->user();
        $this->service->delete($contact, $user, $request->header('X-Correlation-ID'));

        return response()->json(null, 204);
    }
}
