<?php

namespace App\Services\Catalog;

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductModerationService
{
    public function decide(User $user, int $productId, string $decision, mixed $reason = null, ?string $ip = null): Product
    {
        return DB::transaction(function () use ($user, $productId, $decision, $reason, $ip) {
            $actor = $user->fresh();
            abort_unless($actor && $actor->isAdmin() && ! $actor->isLocked(), 403);
            $reference = Product::findOrFail($productId);
            // Use the same vendor -> product lock order as Vendor mutations.
            $vendor = Vendor::whereKey($reference->vendor_id)->lockForUpdate()->firstOrFail();
            $product = Product::whereKey($productId)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('moderate', $product);
            $reason = is_string($reason) ? trim($reason) : $reason;
            Validator::make(['decision' => $decision, 'reason' => $reason], [
                'decision' => ['required', Rule::in(['approve', 'reject', 'hide', 'restore'])],
                'reason' => [Rule::requiredIf(in_array($decision, ['reject', 'hide'], true)), 'nullable', 'string', 'max:255'],
            ], ['reason.required' => 'Vui lòng nhập lý do từ chối hoặc ẩn sản phẩm.',
                'reason.string' => 'Lý do phải là văn bản.', 'reason.max' => 'Lý do có tối đa 255 ký tự.'])->validate();
            $transitions = ['approve' => ['pending', 'approved'], 'reject' => ['pending', 'rejected'],
                'hide' => ['approved', 'hidden'], 'restore' => ['hidden', 'approved']];
            [$from, $to] = $transitions[$decision];
            if ($product->status !== $from) {
                throw ValidationException::withMessages(['product' => 'Trạng thái sản phẩm đã thay đổi hoặc không cho phép thao tác này. Vui lòng tải lại trang.']);
            }
            if (in_array($decision, ['approve', 'restore'], true)) {
                if ($vendor->status !== 'approved' || ! $vendor->owner || $vendor->owner->isLocked()) {
                    throw ValidationException::withMessages(['product' => 'Chỉ được duyệt hoặc hiện sản phẩm của cửa hàng đã duyệt và chủ cửa hàng không bị khóa.']);
                }
                (new ProductSubmissionValidator)->validate($product);
            }
            $old = $product->only(['status', 'reject_reason', 'moderated_by', 'moderated_at']);
            $product->update(['status' => $to, 'reject_reason' => in_array($decision, ['reject', 'hide'], true) ? $reason : null,
                'moderated_by' => $actor->id, 'moderated_at' => now()]);
            AuditLog::create(['actor_id' => $actor->id, 'action' => 'product.'.$decision, 'entity_type' => 'product',
                'entity_id' => $product->id, 'old_values' => $old,
                'new_values' => $product->only(['status', 'reject_reason', 'moderated_by', 'moderated_at']), 'ip' => $ip]);

            return $product;
        });
    }
}
