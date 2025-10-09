<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCompanyRequest;
use App\Http\Requests\UpdateCompanyRequest;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use App\Services\CompanyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CompanyController extends Controller
{
    public function __construct(private readonly CompanyService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Company::class);

        $query = Company::query()->withCount('contacts');

        if ($search = $request->query('search')) {
            $like = '%' . $search . '%';
            $query->where(fn ($builder) => $builder
                ->where('name', 'like', $like)
                ->orWhere('domain', 'like', $like));
        }

        if ($tag = $request->query('tag')) {
            $query->whereJsonContains('tags', $tag);
        }

        $companies = $query->orderByDesc('updated_at')->paginate();

        return CompanyResource::collection($companies);
    }

    public function store(StoreCompanyRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $company = $this->service->create($request->validated(), $user, $request->header('X-Correlation-ID'));

        return CompanyResource::make($company->loadCount('contacts'))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Company $company): CompanyResource
    {
        $this->authorize('view', $company);

        return CompanyResource::make($company->loadCount('contacts'));
    }

    public function update(UpdateCompanyRequest $request, Company $company): CompanyResource
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $updated = $this->service->update($company, $request->validated(), $user, $request->header('X-Correlation-ID'));

        return CompanyResource::make($updated->loadCount('contacts'));
    }

    public function destroy(Request $request, Company $company): JsonResponse
    {
        $this->authorize('delete', $company);

        /** @var \App\Models\User $user */
        $user = $request->user();
        $this->service->delete($company, $user, $request->header('X-Correlation-ID'));

        return response()->json(null, 204);
    }
}
