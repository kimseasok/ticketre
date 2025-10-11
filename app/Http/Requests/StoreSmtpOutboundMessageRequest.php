<?php

namespace App\Http\Requests;


class StoreSmtpOutboundMessageRequest extends ApiFormRequest
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
            'ticket_id' => ['required', 'integer', 'exists:tickets,id'],
            'message_id' => ['nullable', 'integer', 'exists:messages,id'],
            'subject' => ['required', 'string', 'max:255'],
            'body_html' => ['nullable', 'string', 'required_without:body_text'],
            'body_text' => ['nullable', 'string', 'required_without:body_html'],
            'from_email' => ['required', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:255'],
            'mailer' => ['nullable', 'string', 'max:64'],
            'to' => ['required', 'array', 'min:1'],
            'to.*.email' => ['required', 'email', 'max:255'],
            'to.*.name' => ['nullable', 'string', 'max:255'],
            'cc' => ['nullable', 'array'],
            'cc.*.email' => ['required_with:cc', 'email', 'max:255'],
            'cc.*.name' => ['nullable', 'string', 'max:255'],
            'bcc' => ['nullable', 'array'],
            'bcc.*.email' => ['required_with:bcc', 'email', 'max:255'],
            'bcc.*.name' => ['nullable', 'string', 'max:255'],
            'reply_to' => ['nullable', 'array'],
            'reply_to.*.email' => ['required_with:reply_to', 'email', 'max:255'],
            'reply_to.*.name' => ['nullable', 'string', 'max:255'],
            'headers' => ['nullable', 'array'],
            'headers.*' => ['nullable', 'string', 'max:1024'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*.disk' => ['nullable', 'string', 'max:64'],
            'attachments.*.path' => ['required_with:attachments', 'string', 'max:2048'],
            'attachments.*.name' => ['nullable', 'string', 'max:255'],
            'attachments.*.mime_type' => ['nullable', 'string', 'max:255'],
            'attachments.*.size' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
