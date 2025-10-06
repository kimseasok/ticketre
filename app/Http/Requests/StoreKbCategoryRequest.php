<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Validation\Rule;

class StoreKbCategoryRequest extends ApiFormRequest
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
        $user = $this->userOrFail();
        $brandId = $this->brandId($user);

        return [
            'brand_id' => [
                'required',
                'integer',
                Rule::exists('brands', 'id')->where('tenant_id', $user->tenant_id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('kb_categories', 'slug')
                    ->where('tenant_id', $user->tenant_id)
                    ->where('brand_id', $brandId),
            ],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('kb_categories', 'id')
                    ->where('tenant_id', $user->tenant_id)
                    ->where('brand_id', $brandId),
            ],
            'order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    protected function brandId(User $user): ?int
    {
        $brandId = $this->input('brand_id');

        if (is_numeric($brandId)) {
            return (int) $brandId;
        }

        return $user->brand_id;
    }
}
