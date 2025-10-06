<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Validation\Rule;

class StoreKbArticleRequest extends ApiFormRequest
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
            'category_id' => [
                'required',
                'integer',
                Rule::exists('kb_categories', 'id')
                    ->where('tenant_id', $user->tenant_id)
                    ->where('brand_id', $brandId),
            ],
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('kb_articles', 'slug')
                    ->where('tenant_id', $user->tenant_id)
                    ->where('brand_id', $brandId)
                    ->where('locale', $this->input('locale', 'en')),
            ],
            'locale' => ['required', 'string', 'max:10'],
            'status' => ['required', 'string', Rule::in(['draft', 'published', 'archived'])],
            'content' => ['required', 'string'],
            'excerpt' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
            'metadata.*' => ['nullable'],
            'published_at' => ['nullable', 'date'],
            'author_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('tenant_id', $user->tenant_id),
            ],
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
