<?php

namespace App\Http\Requests;

use App\Models\Contact;
use Illuminate\Validation\Rule;

class StoreContactRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user ? $user->can('create', Contact::class) : false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = $this->resolveTenantId();

        return [
            'company_id' => [
                'nullable',
                'integer',
                Rule::exists('companies', 'id')->where(fn ($query) => $tenantId ? $query->where('tenant_id', $tenantId) : $query),
            ],
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('contacts', 'email')->where(function ($query) use ($tenantId) {
                    if ($tenantId) {
                        $query->where('tenant_id', $tenantId);
                    }

                    return $query->whereNull('deleted_at');
                }),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'metadata' => ['sometimes', 'array'],
            'gdpr_marketing_opt_in' => ['required', 'boolean'],
            'gdpr_tracking_opt_in' => ['required', 'boolean'],
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
