<?php

namespace App\Http\Requests;

use App\Models\Contact;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreContactRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        return Gate::forUser($user)->allows('create', Contact::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id ?? app('currentTenant')?->getKey() ?? 0;

        return [
            'company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')->where('tenant_id', $tenantId)],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', $this->uniqueEmailRule($tenantId)],
            'phone' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
            'metadata.*' => ['nullable'],
            'gdpr_consent' => ['required', 'boolean'],
            'gdpr_consented_at' => ['nullable', 'date'],
            'gdpr_consent_method' => ['required_if:gdpr_consent,1', 'string', 'max:255'],
            'gdpr_consent_source' => ['nullable', 'string', 'max:255'],
            'gdpr_notes' => ['nullable', 'string'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['integer', Rule::exists('contact_tags', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at')],
        ];
    }

    protected function uniqueEmailRule(?int $tenantId)
    {
        return Rule::unique('contacts', 'email')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at');
    }
}
