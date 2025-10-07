<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsurePermission;
use App\Http\Requests\AuditLogIndexRequest;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\KbArticle;
use App\Models\KbCategory;
use App\Models\Message;
use App\Models\Ticket;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class AuditLogController extends Controller
{
    public function __construct()
    {
        $this->middleware(EnsurePermission::class.':audit_logs.view')->only('index');
    }

    public function index(AuditLogIndexRequest $request): AnonymousResourceCollection
    {
        $this->authorizeForRequest($request, 'viewAny', AuditLog::class);

        $filters = $request->validated();

        $typeMap = [
            'ticket' => Ticket::class,
            'contact' => Contact::class,
            'message' => Message::class,
            'kb_article' => KbArticle::class,
            'kb_category' => KbCategory::class,
        ];

        if (isset($filters['auditable_type'])) {
            $filters['auditable_type'] = $typeMap[$filters['auditable_type']];
        }

        $query = AuditLog::query()->with(['user']);

        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;
        if ($tenant) {
            $query->where('tenant_id', $tenant->getKey());
        }

        $brand = app()->bound('currentBrand') ? app('currentBrand') : null;
        if ($brand) {
            $query->where(function ($inner) use ($brand) {
                $inner->where('brand_id', $brand->getKey())
                    ->orWhereNull('brand_id');
            });
        }

        if (! empty($filters['auditable_type'])) {
            $query->where('auditable_type', $filters['auditable_type']);
        }

        if (! empty($filters['auditable_id'])) {
            $query->where('auditable_id', $filters['auditable_id']);
        }

        if (! empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (! empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        $perPage = $filters['per_page'] ?? 15;

        $logs = $query->latest()->paginate($perPage);

        return AuditLogResource::collection($logs);
    }

    protected function authorizeForRequest(Request $request, string $ability, mixed $arguments): void
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
