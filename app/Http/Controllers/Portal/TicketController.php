<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\PortalTicketFormRequest;
use App\Models\TicketSubmission;
use App\Notifications\TicketPortalSubmissionConfirmation;
use App\Services\PortalTicketSubmissionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Notification;

class TicketController extends Controller
{
    public function create(): View
    {
        return view('portal.tickets.create', [
            'availableTags' => $this->availableTags(),
        ]);
    }

    public function store(PortalTicketFormRequest $request, PortalTicketSubmissionService $service): RedirectResponse
    {
        $payload = $request->validated();
        $payload['ip_address'] = $request->ip();
        $payload['user_agent'] = $request->userAgent();

        $submission = $service->submit(
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

        return redirect()
            ->route('portal.tickets.confirmation', ['submission' => $submission->getKey()])
            ->with('portal_submission_reference', sprintf('TKT-%06d', $submission->ticket_id));
    }

    public function confirmation(TicketSubmission $submission): View
    {
        $submission->load(['ticket', 'contact']);

        return view('portal.tickets.confirmation', [
            'submission' => $submission,
            'reference' => sprintf('TKT-%06d', $submission->ticket_id),
        ]);
    }

    /**
     * @return array<int, string>
     */
    protected function availableTags(): array
    {
        return ['support', 'billing', 'technical', 'feedback', 'sales'];
    }
}
