<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsurePermission;
use App\Http\Requests\StoreCompanyRequest;
use App\Http\Requests\UpdateCompanyRequest;
use App\Http\Resources\CompanyResource;
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
        $this->middleware(EnsurePermission::class.':companies.view')->only(['index', 'show']);
        $this->middleware(EnsurePermission::class.':companies.manage')->only(['store', 'update', 'destroy']);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorizeRequest($request, 'viewAny', Company::class);

        $query = Company::query()->orderBy('name');

        if ($search = $request->query('search')) {
            $like = '%'.$search.'%';
            $query->where(fn ($builder) => $builder
                ->where('name', 'like', $like)
                ->orWhere('domain', 'like', $like));
        }

        $companies = $query->get();

        return CompanyResource::collection($companies);
    }

    public function store(StoreCompanyRequest $request): JsonResponse
    {
        $company = $this->service->create($request->validated(), $request->user());

        return CompanyResource::make($company)
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Company $company): CompanyResource
    {
        $this->authorizeRequest($request, 'view', $company);

        return CompanyResource::make($company);
    }

    public function update(UpdateCompanyRequest $request, Company $company): CompanyResource
    {
        $company = $this->service->update($company, $request->validated(), $request->user());

        return CompanyResource::make($company);
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
