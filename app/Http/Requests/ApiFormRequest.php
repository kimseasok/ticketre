<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

abstract class ApiFormRequest extends FormRequest
{
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'error' => [
                'code' => 'ERR_VALIDATION',
                'message' => $validator->errors()->first() ?? 'Validation failed.',
                'details' => $validator->errors()->toArray(),
            ],
        ], 422));
    }

    protected function userOrFail(): User
    {
        $user = $this->user();

        if ($user instanceof User) {
            return $user;
        }

        throw new HttpResponseException(response()->json([
            'error' => [
                'code' => 'ERR_UNAUTHENTICATED',
                'message' => 'Authentication required.',
            ],
        ], 401));
    }
}
