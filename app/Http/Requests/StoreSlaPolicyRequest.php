<?php

namespace App\Http\Requests;

use App\Models\SlaPolicy;
use App\Models\Ticket;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreSlaPolicyRequest extends ApiFormRequest
{
    /**
     * @var array<int, string>
     */
    protected array $daysOfWeek = [
        'monday',
        'tuesday',
        'wednesday',
        'thursday',
        'friday',
        'saturday',
        'sunday',
    ];

    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        return Gate::forUser($user)->allows('create', SlaPolicy::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id ?? 0;
        $timezones = \DateTimeZone::listIdentifiers();

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('sla_policies', 'slug')->where('tenant_id', $tenantId)],
            'brand_id' => ['nullable', 'integer', Rule::exists('brands', 'id')->where('tenant_id', $tenantId)],
            'timezone' => ['required', 'string', Rule::in($timezones)],
            'business_hours' => ['nullable', 'array'],
            'business_hours.*.day' => ['required_with:business_hours.*', 'string', Rule::in($this->daysOfWeek)],
            'business_hours.*.start' => ['required_with:business_hours.*', 'date_format:H:i'],
            'business_hours.*.end' => ['required_with:business_hours.*', 'date_format:H:i'],
            'holiday_exceptions' => ['nullable', 'array'],
            'holiday_exceptions.*' => ['string', 'date_format:Y-m-d'],
            'default_first_response_minutes' => ['nullable', 'integer', 'min:0', 'max:43200'],
            'default_resolution_minutes' => ['nullable', 'integer', 'min:0', 'max:43200'],
            'enforce_business_hours' => ['sometimes', 'boolean'],
            'targets' => ['nullable', 'array'],
            'targets.*.channel' => ['required_with:targets', 'string', Rule::in($this->ticketChannels())],
            'targets.*.priority' => ['required_with:targets', 'string', 'max:50'],
            'targets.*.first_response_minutes' => ['nullable', 'integer', 'min:0', 'max:43200'],
            'targets.*.resolution_minutes' => ['nullable', 'integer', 'min:0', 'max:43200'],
            'targets.*.use_business_hours' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $hours = $this->input('business_hours');

        if (is_array($hours)) {
            foreach ($hours as $index => $definition) {
                if (is_array($definition) && isset($definition['day'])) {
                    $hours[$index]['day'] = strtolower((string) $definition['day']);
                }
            }

            $this->merge(['business_hours' => $hours]);
        }
    }

    /**
     * @return array<int, string>
     */
    protected function ticketChannels(): array
    {
        return [
            Ticket::CHANNEL_AGENT,
            Ticket::CHANNEL_PORTAL,
            Ticket::CHANNEL_EMAIL,
            Ticket::CHANNEL_CHAT,
            Ticket::CHANNEL_API,
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator): void {
            $hours = $this->input('business_hours');

            if (! is_array($hours)) {
                return;
            }

            foreach ($hours as $index => $definition) {
                if (! is_array($definition) || ! isset($definition['start'], $definition['end'])) {
                    continue;
                }

                if ($definition['start'] >= $definition['end']) {
                    $validator->errors()->add("business_hours.$index.end", 'The end time must be after the start time.');
                }
            }
        });
    }
}
