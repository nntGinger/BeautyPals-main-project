<?php

namespace App\Http\Requests\Vendor;

use App\Models\Product;
use App\Services\Catalog\ProductInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

abstract class ProductRequest extends FormRequest
{
    private ?Product $managedProduct = null;

    public function product(): ?Product
    {
        if ($this->route('product') === null) {
            return null;
        }

        return $this->managedProduct ??= $this->user()->vendor->products()->findOrFail($this->route('product'));
    }

    public function authorize(): bool
    {
        $product = $this->product();

        return Gate::allows($product ? 'update' : 'create', $product ?? Product::class);
    }

    protected function prepareForValidation(): void
    {
        $this->replace(ProductInput::normalize($this->all()));
    }

    public function rules(): array
    {
        return ProductInput::rules($this->all(), $this->product());
    }

    public function messages(): array
    {
        return ProductInput::messages();
    }

    public function attributes(): array
    {
        return ProductInput::attributes();
    }

    public function payload(): array
    {
        return ProductInput::allowlist($this->validated());
    }
}
