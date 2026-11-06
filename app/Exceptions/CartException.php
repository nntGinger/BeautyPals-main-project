<?php

namespace App\Exceptions;

use DomainException;

final class CartException extends DomainException
{
    public static function invalidQuantity(): self
    {
        return new self(
            'Cart item quantity must be greater than zero.'
        );
    }

    public static function skuNotFound(int $skuId): self
    {
        return new self(
            "SKU {$skuId} does not exist."
        );
    }

    public static function skuInactive(int $skuId): self
    {
        return new self(
            "SKU {$skuId} is inactive and cannot be purchased."
        );
    }

    public static function productUnavailable(int $productId): self
    {
        return new self(
            "Product {$productId} is not approved for sale or is hidden."
        );
    }

    public static function vendorUnavailable(int $vendorId): self
    {
        return new self(
            "Vendor {$vendorId} is not approved for selling."
        );
    }

    public static function insufficientStock(
        int $skuId,
        int $requested,
        int $available
    ): self {
        return new self(
            "Insufficient stock for SKU {$skuId}. "
            ."Requested: {$requested}, available: {$available}."
        );
    }

    public static function itemNotFound(int $skuId): self
    {
        return new self(
            "SKU {$skuId} was not found in the user's cart."
        );
    }
}
