<?php

namespace App\Exceptions;

use RuntimeException;

class InventoryException extends RuntimeException
{
    public static function insufficientStock(int $skuId, int $requested, int $available): self
    {
        return new self(
            "Không đủ tồn kho khả dụng cho SKU [{$skuId}]: yêu cầu {$requested}, khả dụng {$available}."
        );
    }

    public static function invalidQuantity(): self
    {
        return new self('Số lượng phải lớn hơn 0.');
    }

    public static function negativeStockNotAllowed(int $batchId): self
    {
        return new self("Thao tác làm âm tồn kho hoặc thấp hơn số lượng đang giữ chỗ tại lô [{$batchId}].");
    }
}
