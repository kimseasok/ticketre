<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Submit a Support Ticket</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@3.4.10/dist/tailwind.min.css">
</head>
<body class="bg-slate-100 text-slate-900">
    <div class="max-w-3xl mx-auto py-12 px-4">
        <div class="mb-8 text-center">
            <h1 class="text-3xl font-semibold mb-2">We're here to help</h1>
            <p class="text-slate-600">Fill out the form below and our support team will get back to you shortly.</p>
        </div>

        @if ($errors->any())
            <div class="mb-6 rounded-lg border border-rose-300 bg-rose-50 p-4 text-rose-700">
                <p class="font-semibold">We couldn't submit your request</p>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('portal.tickets.store') }}" enctype="multipart/form-data" class="space-y-6 rounded-2xl bg-white p-8 shadow-xl">
            @csrf

            <div class="grid gap-6 md:grid-cols-2">
                <div>
                    <label for="name" class="mb-2 block text-sm font-medium text-slate-700">Full name<span class="text-rose-500">*</span></label>
                    <input id="name" name="name" type="text" value="{{ old('name') }}" required maxlength="255" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-3 text-base shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                    @error('name')
                        <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="email" class="mb-2 block text-sm font-medium text-slate-700">Email<span class="text-rose-500">*</span></label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required maxlength="255" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-3 text-base shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                    @error('email')
                        <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div>
                <label for="subject" class="mb-2 block text-sm font-medium text-slate-700">Subject<span class="text-rose-500">*</span></label>
                <input id="subject" name="subject" type="text" value="{{ old('subject') }}" required maxlength="255" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-3 text-base shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                @error('subject')
                    <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="message" class="mb-2 block text-sm font-medium text-slate-700">Describe the issue<span class="text-rose-500">*</span></label>
                <textarea id="message" name="message" rows="6" required minlength="10" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-3 text-base shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">{{ old('message') }}</textarea>
                @error('message')
                    <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <p class="mb-2 text-sm font-medium text-slate-700">Tags (optional)</p>
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ($availableTags as $tag)
                        <label class="flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-slate-700">
                            <input type="checkbox" name="tags[]" value="{{ $tag }}" @checked(in_array($tag, old('tags', []))) class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="capitalize">{{ $tag }}</span>
                        </label>
                    @endforeach
                </div>
                @error('tags')
                    <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                @enderror
                @error('tags.*')
                    <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="attachments" class="mb-2 block text-sm font-medium text-slate-700">Attachments</label>
                <input id="attachments" name="attachments[]" type="file" multiple accept=".pdf,.png,.jpg,.jpeg,.txt" class="w-full rounded-lg border border-dashed border-slate-300 bg-white px-4 py-3 text-base shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                <p class="mt-2 text-xs text-slate-500">You can upload up to five files (PDF, PNG, JPG, or TXT, max 10&nbsp;MB each).</p>
                @error('attachments')
                    <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                @enderror
                @error('attachments.*')
                    <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center justify-between">
                <p class="text-xs text-slate-500">Fields marked with <span class="text-rose-500">*</span> are required.</p>
                <button type="submit" class="inline-flex items-center gap-2 rounded-full bg-indigo-600 px-6 py-3 text-sm font-semibold text-white shadow-lg transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">Submit ticket</button>
            </div>
        </form>
    </div>
</body>
</html>
