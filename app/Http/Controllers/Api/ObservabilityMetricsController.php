<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ObservabilityPipeline;
use App\Services\ObservabilityMetricRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ObservabilityMetricsController extends Controller
{
    public function __construct(private readonly ObservabilityMetricRecorder $metricRecorder)
    {
    }

    public function __invoke(Request $request): Response
    {
        if (Gate::denies('viewAny', ObservabilityPipeline::class)) {
            return $this->forbiddenResponse();
        }

        $payload = $this->metricRecorder->export();

        return response($payload, Response::HTTP_OK, [
            'Content-Type' => 'text/plain; version=0.0.4',
        ]);
    }

    protected function forbiddenResponse(): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'ERR_HTTP_403',
                'message' => __('This action is unauthorized.'),
            ],
        ], Response::HTTP_FORBIDDEN);
    }
}
