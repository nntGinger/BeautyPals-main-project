<?php

namespace App\Services\Catalog;

use App\Models\Product;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class ProductInput
{
    public static function normalize(array $input): array
    {
        if (! array_key_exists('ingredients', $input)) {
            $input['ingredients'] = [];
        }
        foreach (['name', 'description', 'brand_id'] as $field) {
            if (is_string($input[$field] ?? null)) {
                $input[$field] = trim($input[$field]);
                if ($field !== 'name' && $input[$field] === '') {
                    $input[$field] = null;
                }
            }
        }
        if (is_array($input['skus'] ?? null)) {
            foreach ($input['skus'] as &$sku) {
                if (! is_array($sku)) {
                    continue;
                }
                foreach (['sku_code', 'variant_name', 'size', 'id'] as $field) {
                    if (is_string($sku[$field] ?? null)) {
                        $sku[$field] = trim($sku[$field]);
                        if (in_array($field, ['size', 'id'], true) && $sku[$field] === '') {
                            $sku[$field] = null;
                        }
                    }
                }
                if (is_string($sku['sku_code'] ?? null)) {
                    $sku['sku_code'] = mb_strtoupper($sku['sku_code']);
                }
            }
            unset($sku);
        }

        return $input;
    }

    public static function rules(array $input, ?Product $product): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:200'],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
            'brand_id' => ['nullable', 'integer', Rule::exists('brands', 'id')],
            'description' => ['nullable', 'string', 'max:10000', function ($attribute, $value, $fail) {
                if (is_string($value) && strlen($value) > 65535) {
                    $fail('Mô tả vượt quá 65.535 byte UTF-8.');
                }
            }],
            'skus' => ['bail', 'required', 'array', 'min:1', 'max:20', function ($attribute, $value, $fail) {
                foreach (array_keys($value) as $key) {
                    if (! is_int($key) || $key < 0) {
                        $fail('Danh sách thuộc tính phải có chỉ số nguyên không âm.');
                        break;
                    }
                }
            }],
            'skus.*' => ['required', 'array'],
            'skus.*.id' => $product
                ? ['nullable', 'integer', 'distinct', Rule::exists('skus', 'id')->where('product_id', $product->id)]
                : ['prohibited'],
            'skus.*.sku_code' => ['required', 'string', 'max:60', 'distinct'],
            'skus.*.variant_name' => ['required', 'string', 'max:100'],
            'skus.*.price' => ['bail', 'required', function ($attribute, $value, $fail) {
                if (! is_int($value) && ! is_string($value)) {
                    $fail('Giá bán phải là số nguyên hoặc chuỗi chữ số.');
                }
            }, 'regex:/\A[0-9]+\z/', 'integer', 'min:0', 'max:1000000000'],
            'skus.*.size' => ['nullable', 'string', 'max:50'],
            'skus.*.is_active' => ['required', Rule::in([0, 1, '0', '1'])],
            'images' => ['sometimes', 'array', 'max:8'],
            'images.*' => ['bail', 'required', 'file', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'max:5120', function ($attribute, $file, $fail) {
                if (@getimagesize($file->getRealPath()) === false) {
                    $fail('Nội dung tệp không phải ảnh hợp lệ.');
                }
            }],
            'delete_image_ids' => $product ? ['sometimes', 'array'] : ['prohibited'],
            'delete_image_ids.*' => ['required', 'integer', 'distinct', Rule::exists('product_images', 'id')->where('product_id', $product?->id ?? 0)],
            'ingredients' => ['bail', 'present', 'array', 'max:100', function ($attribute, $value, $fail) {
                foreach (array_keys($value) as $key) {
                    if (! is_int($key) || $key < 0) {
                        $fail('Danh sách thành phần phải có chỉ số nguyên không âm.');
                        break;
                    }
                }
            }],
            'ingredients.*' => ['required', 'array'],
            'ingredients.*.ingredient_id' => ['required', 'integer', 'distinct', Rule::exists('ingredients', 'id')],
            'ingredients.*.position' => ['bail', 'required', 'integer', 'between:1,100', 'distinct'],
        ];
        $position = 0;
        foreach (is_array($input['ingredients'] ?? null) ? $input['ingredients'] : [] as $index => $ingredient) {
            $expected = ++$position;
            if (! is_int($index) || $index < 0 || ! is_array($ingredient)) {
                continue;
            }
            $rules["ingredients.$index.position"] = ['bail', 'required', 'integer', 'between:1,100', 'distinct',
                function ($attribute, $value, $fail) use ($expected) {
                    if ((int) $value !== $expected) {
                        $fail('Thứ tự thành phần phải liên tục từ 1 theo thứ tự hiển thị.');
                    }
                }];
        }
        $ownedIds = $product ? $product->skus()->pluck('id')->all() : [];
        foreach (is_array($input['skus'] ?? null) ? $input['skus'] : [] as $index => $sku) {
            if (! is_int($index) || $index < 0 || ! is_array($sku)) {
                continue;
            }
            $id = $sku['id'] ?? null;
            $unique = Rule::unique('skus', 'sku_code');
            if (is_scalar($id) && in_array((string) $id, array_map('strval', $ownedIds), true)) {
                $unique->ignore($id);
            }
            $rules["skus.$index.sku_code"] = ['required', 'string', 'max:60', 'distinct', $unique];
        }

        return $rules;
    }

    public static function messages(): array
    {
        return [
            'required' => 'Vui lòng nhập :attribute.',
            'string' => ':attribute phải là chuỗi văn bản.',
            'integer' => ':attribute phải là số nguyên.',
            'exists' => ':attribute không hợp lệ.',
            'unique' => ':attribute đã được sử dụng.',
            'distinct' => ':attribute bị lặp trong danh sách.',
            'prohibited' => 'Không được chỉ định ID thuộc tính khi tạo sản phẩm.',
            'skus.*.price.regex' => 'Giá bán phải là số nguyên từ 0 đến 1.000.000.000 VND, không có dấu phân cách.',
            'skus.*.price.min' => 'Giá bán phải từ 0 đến 1.000.000.000 VND.',
            'skus.*.price.max' => 'Giá bán phải từ 0 đến 1.000.000.000 VND.',
            'skus.*.is_active.in' => 'Trạng thái thuộc tính chỉ nhận 0 hoặc 1.',
            'skus.min' => 'Sản phẩm cần ít nhất một thuộc tính.',
            'skus.max' => 'Sản phẩm có tối đa 20 thuộc tính.',
            'images.max' => 'Sản phẩm có tối đa 8 ảnh.',
            'images.*.image' => 'Tệp phải là ảnh JPEG, PNG hoặc WebP hợp lệ.',
            'images.*.mimetypes' => 'Chỉ chấp nhận ảnh JPEG, PNG hoặc WebP.',
            'images.*.max' => 'Mỗi ảnh có dung lượng tối đa 5 MiB.',
            'images.*.uploaded' => 'Không tải được ảnh. Vui lòng kiểm tra dung lượng và chọn lại tệp.',
            'delete_image_ids.*.exists' => 'Ảnh được chọn không thuộc sản phẩm này.',
            'ingredients.array' => 'Danh sách thành phần không hợp lệ.',
            'ingredients.max' => 'Sản phẩm có tối đa 100 thành phần.',
            'ingredients.*.ingredient_id.exists' => 'Thành phần không còn tồn tại. Vui lòng chọn lại từ danh mục.',
            'ingredients.*.ingredient_id.distinct' => 'Không được chọn lặp thành phần.',
            'ingredients.*.position.between' => 'Thứ tự thành phần phải từ 1 đến 100.',
            'ingredients.*.position.distinct' => 'Thứ tự thành phần bị lặp.',
        ];
    }

    public static function attributes(): array
    {
        return ['name' => 'tên sản phẩm', 'category_id' => 'danh mục', 'brand_id' => 'thương hiệu',
            'description' => 'mô tả', 'skus' => 'danh sách thuộc tính', 'skus.*.id' => 'ID thuộc tính',
            'skus.*.sku_code' => 'mã thuộc tính', 'skus.*.variant_name' => 'tên thuộc tính',
            'skus.*.price' => 'giá bán', 'skus.*.size' => 'dung tích/kích thước', 'skus.*.is_active' => 'trạng thái thuộc tính',
            'ingredients.*.ingredient_id' => 'thành phần', 'ingredients.*.position' => 'thứ tự thành phần'];
    }

    public static function allowlist(array $data): array
    {
        $result = Arr::only($data, ['name', 'category_id', 'brand_id', 'description', 'images', 'delete_image_ids']);
        $result['brand_id'] ??= null;
        $result['description'] ??= null;
        $result['skus'] = array_map(fn ($sku) => Arr::only($sku, ['id', 'sku_code', 'variant_name', 'price', 'size', 'is_active']), array_values($data['skus']));
        $result['ingredients'] = array_map(fn ($row) => Arr::only($row, ['ingredient_id', 'position']), array_values($data['ingredients'] ?? []));

        return $result;
    }
}
