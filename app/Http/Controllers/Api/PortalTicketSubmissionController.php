<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PortalTicketSubmissionRequest;
use App\Http\Resources\PortalTicketSubmissionResource;
use App\Notifications\TicketPortalSubmissionConfirmation;
use App\Services\PortalTicketSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Notification;

class PortalTicketSubmissionController extends Controller
{
    public function __construct(private readonly PortalTicketSubmissionService $service)
    {
    }

    public function store(PortalTicketSubmissionRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $payload['ip_address'] = $request->ip();
        $payload['user_agent'] = $request->userAgent();

        $submission = $this->service->submit(
            $payload,
            $request->file('attachments', []),
            $request->header('X-Correlation-ID')
        );

        if (! $submission->relationLoaded('contact')) {
            $submission->load('contact');
        }

        if ($submission->contact && $submission->contact->email) {
            Notification::route('mail', $submission->contact->email)
                ->notify(new TicketPortalSubmissionConfirmation($submission));
        }

        return PortalTicketSubmissionResource::make($submission)->response()->setStatusCode(201);
    }
}
