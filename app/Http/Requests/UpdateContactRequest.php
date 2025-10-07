<?php

namespace App\Http\Requests;

use App\Models\Contact;
use Illuminate\Validation\Rule;

class UpdateContactRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $contact = $this->route('contact');

        if (! $user || ! $contact instanceof Contact) {
            return false;
        }

        return $user->can('update', $contact);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = $this->resolveTenantId();
        $contact = $this->route('contact');

        $contactId = $contact instanceof Contact ? $contact->getKey() : null;

        return [
            'company_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('companies', 'id')->where(fn ($query) => $tenantId ? $query->where('tenant_id', $tenantId) : $query),
            ],
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'email',
                'max:255',
                Rule::unique('contacts', 'email')
                    ->ignore($contactId)
                    ->where(function ($query) use ($tenantId) {
                        if ($tenantId) {
                            $query->where('tenant_id', $tenantId);
                        }

                        return $query->whereNull('deleted_at');
                    }),
            ],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'metadata' => ['sometimes', 'array'],
            'gdpr_marketing_opt_in' => ['sometimes', 'boolean'],
            'gdpr_tracking_opt_in' => ['sometimes', 'boolean'],
            'gdpr_consent_recorded_at' => ['sometimes', 'nullable', 'date'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'max:64'],
        ];
    }

    protected function resolveTenantId(): ?int
    {
        if (app()->bound('currentTenant') && app('currentTenant')) {
            return (int) app('currentTenant')->getKey();
        }

        return $this->user()?->tenant_id;
    }
}
