<?php

namespace App\Services\Catalog;

use App\Models\Product;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Throwable;

class ProductSubmissionValidator
{
    public function validate(Product $product): void
    {
        $skus = $product->skus()->lockForUpdate()->get();
        $images = $product->images()->lockForUpdate()->get();
        $ingredients = $product->ingredients()->lockForUpdate()->get();
        $data = ProductInput::normalize([
            'name' => $product->name,
            'category_id' => $product->category_id,
            'brand_id' => $product->brand_id,
            'description' => $product->description,
            'skus' => $skus->map(fn ($sku) => [
                'id' => $sku->id, 'sku_code' => $sku->sku_code,
                'variant_name' => $sku->variant_name, 'price' => $sku->price,
                'size' => $sku->size, 'is_active' => (int) $sku->is_active,
            ])->all(),
        ]);
        $validator = Validator::make($data, ProductInput::rules($data, $product), ProductInput::messages(), ProductInput::attributes());
        $validator->after(function ($validator) use ($skus, $images, $ingredients): void {
            if (! $skus->contains(fn ($sku) => $sku->is_active)) {
                $validator->errors()->add('skus', 'Cần ít nhất một thuộc tính đang hoạt động để gửi duyệt.');
            }
            if ($images->isEmpty() || $images->count() > 8) {
                $validator->errors()->add('images', 'Cần từ 1 đến 8 ảnh hợp lệ trước khi gửi duyệt.');
            }
            foreach ($images as $image) {
                try {
                    $disk = Storage::disk('public');
                    $path = $image->path;
                    $safe = strlen($path) <= 255 && str_starts_with($path, 'products/')
                        && ! preg_match('~(^|[\\\\/])\.\.([\\\\/]|$)~', $path);
                    $info = $safe && $disk->exists($path) ? @getimagesize($disk->path($path)) : false;
                    if (! $info || ! in_array($info['mime'] ?? '', ['image/jpeg', 'image/png', 'image/webp'], true)
                        || $disk->size($path) > 5242880) {
                        $validator->errors()->add('images', 'Ảnh đã lưu bị thiếu, sai định dạng hoặc vượt 5 MiB. Hãy sửa sản phẩm để thay hoặc bỏ ảnh lỗi.');
                        break;
                    }
                } catch (Throwable) {
                    $validator->errors()->add('images', 'Không đọc được ảnh đã lưu. Vui lòng kiểm tra lại ảnh sản phẩm.');
                    break;
                }
            }
            if ($images->isNotEmpty() && $images->pluck('sort')->map(fn ($sort) => (int) $sort)->all() !== range(1, $images->count())) {
                $validator->errors()->add('images', 'Thứ tự ảnh không hợp lệ. Vui lòng lưu lại sản phẩm.');
            }
            if ($ingredients->isEmpty() || $ingredients->count() > 100) {
                $validator->errors()->add('ingredients', 'Cần từ 1 đến 100 thành phần trước khi gửi duyệt.');
            } elseif ($ingredients->pluck('pivot.position')->map(fn ($position) => (int) $position)->all() !== range(1, $ingredients->count())) {
                $validator->errors()->add('ingredients', 'Thứ tự thành phần phải liên tục từ 1 đến số thành phần.');
            }
        });
        $validator->validate();
    }
}
