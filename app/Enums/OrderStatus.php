<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case PACKED = 'packed';
    case SHIPPED = 'shipped';
    case DELIVERED = 'delivered';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Chờ xác nhận',
            self::CONFIRMED => 'Đã xác nhận',
            self::PACKED => 'Đã đóng gói',
            self::SHIPPED => 'Đang giao',
            self::DELIVERED => 'Đã giao',
            self::COMPLETED => 'Hoàn tất',
            self::CANCELLED => 'Đã hủy',
            self::REJECTED => 'Đã từ chối',
        };
    }
}
