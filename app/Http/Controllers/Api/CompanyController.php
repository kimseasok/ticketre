<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCompanyRequest;
use App\Http\Requests\UpdateCompanyRequest;
use App\Http\Resources\CompanyResource as CompanyApiResource;
use App\Models\Company;
use App\Services\CompanyService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class CompanyController extends Controller
{
    public function __construct(private readonly CompanyService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorizeRequest($request, 'viewAny', Company::class);

        $query = Company::query()->withCount('contacts');

        if ($search = $request->query('search')) {
            $like = '%'.$search.'%';
            $query->where(function ($builder) use ($like) {
                $builder->where('name', 'like', $like)
                    ->orWhere('domain', 'like', $like);
            });
        }

        $query->orderBy('name');

        $companies = $query->paginate($request->integer('per_page', 25))->withQueryString();

        return CompanyApiResource::collection($companies);
    }

    public function store(StoreCompanyRequest $request): JsonResponse
    {
        $company = $this->service->create($request->validated(), $request->user());

        return CompanyApiResource::make($company)
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Company $company): CompanyApiResource
    {
        $this->authorizeRequest($request, 'view', $company);

        $company->loadCount('contacts');

        return CompanyApiResource::make($company);
    }

    public function update(UpdateCompanyRequest $request, Company $company): CompanyApiResource
    {
        $company = $this->service->update($company, $request->validated(), $request->user());

        $company->loadCount('contacts');

        return CompanyApiResource::make($company);
    }

    public function destroy(Request $request, Company $company): JsonResponse
    {
        $this->authorizeRequest($request, 'delete', $company);

        $this->service->delete($company, $request->user());

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
