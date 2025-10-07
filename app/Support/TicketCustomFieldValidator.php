<?php

namespace App\Support;

use App\Exceptions\InvalidCustomFieldException;
use Illuminate\Support\Arr;
use Illuminate\Support\CarbonImmutable;
use Illuminate\Support\Str;

class TicketCustomFieldValidator
{
    /**
     * @param  array<int, mixed>  $fields
     * @return array<int, array<string, mixed>>
     */
    public static function validate(array $fields): array
    {
        $errors = [];
        $normalized = [];
        $keys = [];

        if (count($fields) > 25) {
            $errors['custom_fields'] = 'You may not provide more than 25 custom fields.';
        }

        foreach (array_values($fields) as $index => $field) {
            $path = "custom_fields.$index";

            if (! is_array($field)) {
                $errors[$path] = 'Each custom field must be an object with key, type, and value.';
                continue;
            }

            $key = self::extractKey($field, $index, $errors);
            $type = self::extractType($field, $index, $errors);

            if (! $key || ! $type) {
                continue;
            }

            $lowerKey = Str::lower($key);
            if (in_array($lowerKey, $keys, true)) {
                $errors["$path.key"] = 'Custom field keys must be unique (case-insensitive).';
                continue;
            }

            $keys[] = $lowerKey;

            $value = $field['value'] ?? null;
            $normalizedValue = self::normalizeValue($type, $value, $path, $errors);

            if ($normalizedValue === null && array_key_exists("$path.value", $errors)) {
                continue;
            }

            $normalized[] = [
                'key' => $key,
                'type' => $type,
                'value' => $normalizedValue,
            ];
        }

        if ($errors !== []) {
            throw new InvalidCustomFieldException($errors);
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $field
     */
    protected static function extractKey(array $field, int $index, array &$errors): ?string
    {
        $path = "custom_fields.$index.key";
        $key = Arr::get($field, 'key');

        if (! is_string($key) || trim($key) === '') {
            $errors[$path] = 'The custom field key is required.';
            return null;
        }

        $key = trim($key);

        if (mb_strlen($key) > 64) {
            $errors[$path] = 'Custom field keys must be 64 characters or less.';
            return null;
        }

        if (! preg_match('/^[A-Za-z0-9_.-]+$/', $key)) {
            $errors[$path] = 'Custom field keys may contain only letters, numbers, dots, underscores, and dashes.';
            return null;
        }

        return $key;
    }

    /**
     * @param  array<string, mixed>  $field
     */
    protected static function extractType(array $field, int $index, array &$errors): ?string
    {
        $path = "custom_fields.$index.type";
        $type = Arr::get($field, 'type');

        if (! is_string($type)) {
            $errors[$path] = 'The custom field type is required.';
            return null;
        }

        $type = Str::lower($type);

        $allowed = ['string', 'number', 'boolean', 'date', 'json'];
        if (! in_array($type, $allowed, true)) {
            $errors[$path] = 'The custom field type must be one of: '.implode(', ', $allowed).'.';
            return null;
        }

        return $type;
    }

    protected static function normalizeValue(string $type, mixed $value, string $path, array &$errors): mixed
    {
        $valuePath = "$path.value";

        switch ($type) {
            case 'string':
                if (! is_string($value)) {
                    $errors[$valuePath] = 'String custom fields must provide a string value.';
                    return null;
                }

                return Str::limit($value, 1000, '');
            case 'number':
                if (! is_numeric($value)) {
                    $errors[$valuePath] = 'Number custom fields must provide a numeric value.';
                    return null;
                }

                $numeric = $value + 0;

                return $numeric;
            case 'boolean':
                if (is_bool($value)) {
                    return $value;
                }

                if (in_array($value, ['true', 'false', '1', '0', 1, 0], true)) {
                    return in_array($value, ['true', '1', 1], true);
                }

                $errors[$valuePath] = 'Boolean custom fields must be true or false.';

                return null;
            case 'date':
                if (! is_string($value) && ! $value instanceof \DateTimeInterface) {
                    $errors[$valuePath] = 'Date custom fields must be parsable date strings.';

                    return null;
                }

                try {
                    return CarbonImmutable::parse($value)->toIso8601String();
                } catch (\Throwable) {
                    $errors[$valuePath] = 'Date custom fields must be parsable date strings.';

                    return null;
                }
            case 'json':
                if (is_string($value)) {
                    $decoded = json_decode($value, true);

                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $errors[$valuePath] = 'JSON custom fields must contain valid JSON values.';

                        return null;
                    }

                    $value = $decoded;
                }

                if (! is_array($value)) {
                    $errors[$valuePath] = 'JSON custom fields must be arrays or objects.';

                    return null;
                }

                return self::sanitizeJsonValue($value);
        }

        $errors[$valuePath] = 'Unsupported custom field type.';

        return null;
    }

    /**
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    protected static function sanitizeJsonValue(array $value): array
    {
        return array_map(static function ($item) {
            if (is_array($item)) {
                return self::sanitizeJsonValue($item);
            }

            if (is_bool($item) || is_int($item) || is_float($item) || $item === null) {
                return $item;
            }

            return (string) $item;
        }, $value);
    }
}
