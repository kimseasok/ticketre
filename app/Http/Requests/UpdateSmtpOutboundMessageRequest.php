<?php

namespace App\Http\Requests;

class UpdateSmtpOutboundMessageRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'subject' => ['sometimes', 'string', 'max:255'],
            'body_html' => ['sometimes', 'nullable', 'string'],
            'body_text' => ['sometimes', 'nullable', 'string'],
            'from_email' => ['sometimes', 'email', 'max:255'],
            'from_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'mailer' => ['sometimes', 'nullable', 'string', 'max:64'],
            'to' => ['sometimes', 'array', 'min:1'],
            'to.*.email' => ['required_with:to', 'email', 'max:255'],
            'to.*.name' => ['nullable', 'string', 'max:255'],
            'cc' => ['sometimes', 'nullable', 'array'],
            'cc.*.email' => ['required_with:cc', 'email', 'max:255'],
            'cc.*.name' => ['nullable', 'string', 'max:255'],
            'bcc' => ['sometimes', 'nullable', 'array'],
            'bcc.*.email' => ['required_with:bcc', 'email', 'max:255'],
            'bcc.*.name' => ['nullable', 'string', 'max:255'],
            'reply_to' => ['sometimes', 'nullable', 'array'],
            'reply_to.*.email' => ['required_with:reply_to', 'email', 'max:255'],
            'reply_to.*.name' => ['nullable', 'string', 'max:255'],
            'headers' => ['sometimes', 'nullable', 'array'],
            'headers.*' => ['nullable', 'string', 'max:1024'],
            'attachments' => ['sometimes', 'nullable', 'array', 'max:10'],
            'attachments.*.disk' => ['nullable', 'string', 'max:64'],
            'attachments.*.path' => ['required_with:attachments', 'string', 'max:2048'],
            'attachments.*.name' => ['nullable', 'string', 'max:255'],
            'attachments.*.mime_type' => ['nullable', 'string', 'max:255'],
            'attachments.*.size' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
