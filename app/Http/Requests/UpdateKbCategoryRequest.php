<?php

namespace App\Http\Requests;

use App\Models\KbCategory;
use App\Models\User;
use Illuminate\Validation\Rule;

class UpdateKbCategoryRequest extends ApiFormRequest
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
        /** @var KbCategory|null $category */
        $category = $this->route('kb_category');
        $user = $this->userOrFail();
        $brandId = $this->brandId($user, $category?->brand_id);

        $parentRules = [
            'nullable',
            'integer',
            Rule::exists('kb_categories', 'id')
                ->where('tenant_id', $user->tenant_id)
                ->where('brand_id', $brandId),
        ];

        if ($category) {
            $parentRules[] = Rule::notIn([$category->getKey()]);
        }

        return [
            'brand_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('brands', 'id')->where('tenant_id', $user->tenant_id),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('kb_categories', 'slug')
                    ->where('tenant_id', $user->tenant_id)
                    ->where('brand_id', $brandId)
                    ->ignore($category?->getKey()),
            ],
            'parent_id' => $parentRules,
            'order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    protected function brandId(User $user, ?int $fallback): ?int
    {
        $brandId = $this->input('brand_id');

        if (is_numeric($brandId)) {
            return (int) $brandId;
        }

        if ($fallback !== null) {
            return $fallback;
        }

        return $user->brand_id;
    }
}
