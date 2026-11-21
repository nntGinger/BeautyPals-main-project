<?php

namespace App\Enums;

use App\Exceptions\PaymentException;

enum PaymentStatus: string
{
    case PENDING = 'pending';
    case PAID = 'paid';
    case FAILED = 'failed';

    /**
     * Normalize incoming gateway callback statuses into domain payment states.
     */
    public static function fromGatewayCallback(string $status): self
    {
        return match (strtolower(trim($status))) {
            'pending', 'processing', 'waiting' => self::PENDING,
            'paid', 'success', 'succeeded', '00' => self::PAID,
            'failed', 'fail', 'error', 'cancelled' => self::FAILED,
            default => throw new PaymentException("Trạng thái callback thanh toán [{$status}] không hợp lệ."),
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Chờ thanh toán',
            self::PAID => 'Đã thanh toán',
            self::FAILED => 'Thanh toán thất bại',
        };
    }
}

