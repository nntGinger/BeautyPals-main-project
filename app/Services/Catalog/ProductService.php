<?php

namespace App\Services\Catalog;

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProductService
{
    public function save(User $user, array $data, ?int $productId = null): Product
    {
        $data = ProductInput::normalize($data);
        $imageService = new ProductImageService;
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $newPaths = [];
            $oldPaths = [];
            try {
                $saved = DB::transaction(function () use ($user, $data, $productId, $imageService, &$newPaths, &$oldPaths) {
                    $actor = $this->lockActor($user);
                    $vendor = $actor->vendor;
                    $product = $productId ? $vendor->products()->lockForUpdate()->findOrFail($productId) : null;
                    Gate::forUser($actor)->authorize($product ? 'update' : 'create', $product ?? Product::class);
                    $oldSkus = $product ? $product->skus()->lockForUpdate()->get() : collect();
                    $validated = Validator::make($data, ProductInput::rules($data, $product), ProductInput::messages(), ProductInput::attributes())->validate();
                    $values = ProductInput::allowlist($validated);
                    $keptIds = array_filter(array_column($values['skus'], 'id'));
                    $removed = $oldSkus->whereNotIn('id', $keptIds);
                    foreach ($removed as $sku) {
                        if ($sku->batches()->exists() || $sku->cartItems()->exists() || $sku->orderItems()->exists()) {
                            throw ValidationException::withMessages(['skus' => 'Không thể xóa thuộc tính đã được sử dụng. Hãy giữ dòng và ngừng hoạt động.']);
                        }
                    }
                    $fields = Arr::except($values, ['skus', 'images', 'delete_image_ids', 'ingredients']);
                    if ($product) {
                        $product->update($fields);
                    } else {
                        $product = $vendor->products()->create($fields + ['slug' => $this->slug($values['name']), 'status' => 'draft']);
                    }
                    foreach ($removed as $sku) {
                        $sku->delete();
                    }
                    foreach ($values['skus'] as $row) {
                        $fields = Arr::except($row, 'id');
                        $fields['size'] ??= null;
                        if (! empty($row['id'])) {
                            $oldSkus->firstWhere('id', $row['id'])->update($fields);
                        } else {
                            $product->skus()->create($fields);
                        }
                    }
                    $oldPaths = $imageService->save($product, $values, $newPaths);
                    (new ProductIngredientService)->save($product, $values['ingredients']);

                    return $product;
                });
                $imageService->cleanup($oldPaths, $saved->id, 'after_commit');

                return $saved;
            } catch (Throwable $exception) {
                $imageService->cleanup($newPaths, $productId, 'rollback');
                if (! $exception instanceof QueryException) {
                    throw $exception;
                }
                $code = $exception->errorInfo[1] ?? null;
                if ($code === 1062 && str_contains($exception->getMessage(), 'uq_products_slug')) {
                    if ($attempt < 2) {
                        continue;
                    }
                    throw ValidationException::withMessages(['name' => 'Chưa thể tạo mã sản phẩm duy nhất. Vui lòng thử lại.']);
                }
                if ($code === 1062 && str_contains($exception->getMessage(), 'uq_skus_code')) {
                    throw ValidationException::withMessages(['skus' => 'Mã thuộc tính đã được sử dụng. Vui lòng kiểm tra lại.']);
                }
                if (in_array($code, [1451, 1452], true)) {
                    throw ValidationException::withMessages(['product' => 'Dữ liệu liên quan vừa thay đổi hoặc đang được sử dụng. Vui lòng tải lại sản phẩm.']);
                }
                throw $exception;
            }
        }
    }

    public function submit(User $user, int $productId, ?string $ip = null): Product
    {
        return DB::transaction(function () use ($user, $productId, $ip) {
            $actor = $this->lockActor($user);
            $product = $actor->vendor->products()->lockForUpdate()->findOrFail($productId);
            Gate::forUser($actor)->authorize('submit', $product);
            if (! in_array($product->status, ['draft', 'rejected', 'hidden'], true)) {
                throw ValidationException::withMessages(['product' => 'Chỉ sản phẩm nháp, bị từ chối hoặc đã ẩn mới được gửi duyệt.']);
            }
            (new ProductSubmissionValidator)->validate($product);
            $old = $product->only(['status', 'reject_reason', 'moderated_by', 'moderated_at']);
            $product->update(['status' => 'pending', 'reject_reason' => null, 'moderated_by' => null, 'moderated_at' => null]);
            AuditLog::create(['actor_id' => $actor->id, 'action' => 'product.submit', 'entity_type' => 'product',
                'entity_id' => $product->id, 'old_values' => $old,
                'new_values' => $product->only(['status', 'reject_reason', 'moderated_by', 'moderated_at']), 'ip' => $ip]);

            return $product;
        });
    }

    public function destroy(User $user, int $productId): void
    {
        try {
            $paths = DB::transaction(function () use ($user, $productId) {
                $actor = $this->lockActor($user);
                $product = $actor->vendor->products()->lockForUpdate()->findOrFail($productId);
                Gate::forUser($actor)->authorize('delete', $product);
                if ($product->status !== 'draft') {
                    throw ValidationException::withMessages(['product' => 'Chỉ được xóa sản phẩm nháp chưa có dữ liệu liên quan.']);
                }
                $skus = $product->skus()->lockForUpdate()->get();
                if ($product->reviews()->exists() || $product->wishedByUsers()->exists()) {
                    throw ValidationException::withMessages(['product' => 'Không thể xóa sản phẩm đang có đánh giá hoặc được yêu thích.']);
                }
                foreach ($skus as $sku) {
                    if ($sku->batches()->exists() || $sku->cartItems()->exists() || $sku->orderItems()->exists()) {
                        throw ValidationException::withMessages(['product' => 'Không thể xóa sản phẩm có thuộc tính đã được dùng trong kho, giỏ hàng hoặc đơn hàng.']);
                    }
                }
                $paths = $product->images()->lockForUpdate()->pluck('path')->all();
                $product->delete();

                return $paths;
            });
        } catch (QueryException $exception) {
            if (in_array($exception->errorInfo[1] ?? null, [1451, 1452], true)) {
                throw ValidationException::withMessages(['product' => 'Dữ liệu liên quan vừa thay đổi. Không thể xóa sản phẩm đang được sử dụng.']);
            }
            throw $exception;
        }
        (new ProductImageService)->cleanup($paths, $productId, 'delete_product');
    }

    private function lockActor(User $user): User
    {
        $actor = $user->fresh();
        abort_unless($actor && ! $actor->isLocked() && $actor->isVendor(), 403);
        $vendor = Vendor::where('owner_user_id', $actor->id)->lockForUpdate()->first();
        abort_unless($vendor && $vendor->status === 'approved', 403);
        $actor->setRelation('vendor', $vendor);

        return $actor;
    }

    private function slug(string $name): string
    {
        $base = Str::slug($name) ?: 'product';
        for ($suffix = 0; $suffix < 1000; $suffix++) {
            $ending = $suffix ? '-'.$suffix : '';
            $slug = Str::substr($base, 0, 220 - strlen($ending)).$ending;
            if (! Product::where('slug', $slug)->exists()) {
                return $slug;
            }
        }
        throw ValidationException::withMessages(['name' => 'Tên này có quá nhiều sản phẩm trùng. Vui lòng chọn tên khác.']);
    }
}
