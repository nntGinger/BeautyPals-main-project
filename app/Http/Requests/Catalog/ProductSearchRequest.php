<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ProductSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        // UC04: public, read-only catalogue.
        return true;
    }

    protected function prepareForValidation(): void
    {
        $keyword = $this->input('q');
        if (is_string($keyword)) {
            $this->merge(['q' => trim($keyword)]);
        }
    }

    public function rules(): array
    {
        return [
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'category_id' => ['sometimes', 'integer', 'min:1'],
            'brand_id' => ['sometimes', 'integer', 'min:1'],
            'min_price' => ['sometimes', 'numeric', 'min:0', 'max:999999999999'],
            'max_price' => array_merge(
                ['sometimes', 'numeric', 'min:0', 'max:999999999999'],
                $this->filled('min_price') ? ['gte:min_price'] : []
            ),
            'in_stock' => ['sometimes', 'boolean'],
            'include_descendants' => ['sometimes', 'boolean'],
            'sort' => ['sometimes', Rule::in(['newest', 'price_asc', 'price_desc', 'name_asc', 'name_desc'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:48'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:10000'],
        ];
    }
}
