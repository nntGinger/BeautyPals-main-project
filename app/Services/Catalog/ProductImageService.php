<?php

namespace App\Services\Catalog;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class ProductImageService
{
    public function save(Product $product, array $data, array &$newPaths): array
    {
        $images = $product->images()->lockForUpdate()->get();
        $deletedIds = $data['delete_image_ids'] ?? [];
        $removed = $images->whereIn('id', $deletedIds);
        $remaining = $images->whereNotIn('id', $deletedIds);
        $files = $data['images'] ?? [];
        if ($remaining->count() + count($files) > 8) {
            throw ValidationException::withMessages(['images' => 'Tổng ảnh sau khi lưu không được vượt quá 8. Hãy bỏ bớt ảnh cũ hoặc chọn ít ảnh mới hơn.']);
        }
        // Clear old sort numbers before assigning the final display order.
        foreach ($removed as $image) {
            $image->delete();
        }
        $sort = 1;
        foreach ($remaining as $image) {
            $image->update(['sort' => $sort++]);
        }
        foreach ($files as $file) {
            $path = $file->store('products/'.$product->id, 'public');
            if (! is_string($path) || $path === '') {
                throw new RuntimeException('Không thể lưu ảnh sản phẩm.');
            }
            $newPaths[] = $path;
            $product->images()->create(['path' => $path, 'sort' => $sort++]);
        }

        return $removed->pluck('path')->all();
    }

    public function cleanup(array $paths, ?int $productId, string $action): void
    {
        foreach (array_unique($paths) as $path) {
            try {
                if (! ProductImage::where('path', $path)->exists() && ! Storage::disk('public')->delete($path)) {
                    Log::warning('Không xóa được ảnh sản phẩm.', ['path' => $path, 'product_id' => $productId, 'action' => $action]);
                }
            } catch (Throwable $exception) {
                Log::warning('Lỗi dọn ảnh sản phẩm.', ['path' => $path, 'product_id' => $productId, 'action' => $action, 'error' => $exception->getMessage()]);
            }
        }
    }
}
