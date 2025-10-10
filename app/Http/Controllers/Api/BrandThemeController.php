<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Services\BrandAssetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BrandThemeController extends Controller
{
    public function __construct(private readonly BrandAssetService $assetService)
    {
    }

    public function show(Request $request, Brand $brand): JsonResponse
    {
        $this->authorize('view', $brand);
        /** @var \App\Models\User|null $user */
        $user = $request->user();

        if (! $user) {
            abort(Response::HTTP_UNAUTHORIZED, 'Authentication required.');
        }

        $configuration = $this->assetService->themeConfiguration($brand, $user, $request->header('X-Correlation-ID'));

        return response()->json([
            'data' => $configuration,
        ], Response::HTTP_OK, [
            'Cache-Control' => $configuration['cache_control'],
            'ETag' => $configuration['version'],
            'X-Brand-Theme-Version' => $configuration['version'],
        ]);
    }
}
