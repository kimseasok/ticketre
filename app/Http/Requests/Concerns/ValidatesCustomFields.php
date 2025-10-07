<?php

namespace App\Http\Requests\Concerns;

use App\Exceptions\InvalidCustomFieldException;
use App\Support\TicketCustomFieldValidator;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

trait ValidatesCustomFields
{
    protected array $validatedCustomFields = [];

    protected function customFieldRules(bool $isUpdate = false): array
    {
        $rules = [
            'custom_fields' => ['nullable', 'array', 'max:25'],
            'custom_fields.*.key' => ['required_with:custom_fields', 'string', 'max:64', 'regex:/^[A-Za-z0-9_.-]+$/'],
            'custom_fields.*.type' => ['required_with:custom_fields', Rule::in(['string', 'number', 'boolean', 'date', 'json'])],
            'custom_fields.*.value' => ['present'],
        ];

        if ($isUpdate) {
            array_unshift($rules['custom_fields'], 'sometimes');
            array_unshift($rules['custom_fields.*.key'], 'sometimes');
            array_unshift($rules['custom_fields.*.type'], 'sometimes');
            array_unshift($rules['custom_fields.*.value'], 'sometimes');
        }

        return $rules;
    }

    protected function validateCustomFields(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->has('custom_fields')) {
                $this->validatedCustomFields = [];

                return;
            }

            $fields = $this->input('custom_fields');

            if ($fields === null) {
                $this->validatedCustomFields = [];

                return;
            }

            if (! is_array($fields)) {
                $validator->errors()->add('custom_fields', 'Custom fields must be provided as an array.');

                return;
            }

            try {
                $this->validatedCustomFields = TicketCustomFieldValidator::validate($fields);
            } catch (InvalidCustomFieldException $exception) {
                foreach ($exception->errors() as $path => $message) {
                    $validator->errors()->add($path, $message);
                }
            }
        });
    }

    public function sanitizedCustomFields(bool $onlyWhenPresent = false): ?array
    {
        if ($onlyWhenPresent && ! $this->has('custom_fields')) {
            return null;
        }

        return $this->validatedCustomFields;
    }

    public function sanitizedMetadata(bool $onlyWhenPresent = false): ?array
    {
        if ($onlyWhenPresent && ! $this->has('metadata')) {
            return null;
        }

        $metadata = $this->input('metadata');

        if (! is_array($metadata)) {
            return $onlyWhenPresent ? null : [];
        }

        return $this->sanitizeMetadata($metadata);
    }

    /**
     * @param  array<mixed>  $metadata
     * @return array<mixed>
     */
    protected function sanitizeMetadata(array $metadata): array
    {
        $sanitized = [];

        foreach ($metadata as $key => $value) {
            if (! is_string($key)) {
                $key = (string) $key;
            }

            $sanitized[$key] = $this->sanitizeMetadataValue($value);
        }

        return $sanitized;
    }

    protected function sanitizeMetadataValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return $this->sanitizeMetadata($value);
        }

        if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
            return $value;
        }

        if (is_string($value)) {
            return Str::of($value)->limit(1000, '')->__toString();
        }

        return (string) $value;
    }
}
