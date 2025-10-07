<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ticket submitted</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@3.4.10/dist/tailwind.min.css">
</head>
<body class="bg-slate-100 text-slate-900">
    <div class="max-w-2xl mx-auto py-12 px-4">
        <div class="rounded-2xl bg-white p-10 shadow-xl">
            <div class="mb-6 text-center">
                <div class="mx-auto mb-4 h-14 w-14 rounded-full bg-emerald-100 p-3 text-emerald-600">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-full w-full">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-6.356a9 9 0 11-9 9 9 9 0 019-9z" />
                    </svg>
                </div>
                <h1 class="text-3xl font-semibold">Thanks! Your request is on its way.</h1>
                <p class="mt-3 text-slate-600">We saved your message and assigned it to our support queue. You'll receive updates at <span class="font-medium">{{ $submission->contact?->email }}</span>.</p>
            </div>

            <dl class="space-y-4 text-sm text-slate-700">
                <div class="flex items-start justify-between gap-4">
                    <dt class="font-medium text-slate-500">Reference</dt>
                    <dd class="font-semibold text-slate-900">{{ $reference }}</dd>
                </div>
                <div class="flex items-start justify-between gap-4">
                    <dt class="font-medium text-slate-500">Subject</dt>
                    <dd class="text-right">{{ $submission->subject }}</dd>
                </div>
                <div class="flex items-start justify-between gap-4">
                    <dt class="font-medium text-slate-500">Submitted</dt>
                    <dd class="text-right">{{ optional($submission->submitted_at)->format('F j, Y g:i A') }}</dd>
                </div>
                <div class="space-y-2">
                    <dt class="font-medium text-slate-500">Message</dt>
                    <dd class="rounded-lg bg-slate-50 p-4 text-slate-700">{!! nl2br(e($submission->message)) !!}</dd>
                </div>
            </dl>

            <div class="mt-8 flex flex-wrap items-center justify-between gap-4 text-sm">
                <a href="{{ route('portal.tickets.create') }}" class="inline-flex items-center gap-2 rounded-full border border-slate-300 px-5 py-2 font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-900">Submit another request</a>
                <p class="text-xs text-slate-500">Need to attach more details? Reply to the confirmation email with additional context.</p>
            </div>
        </div>
    </div>
</body>
</html>
