<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_code',
        'customer_id',
        'receiver',
        'phone',
        'address_text',
        'subtotal',
        'shipping_total',
        'discount_total',
        'grand_total',
        'payment_method',
        'payment_status',
        'status',
        'voucher_id',
        'idempotency_key',
        'placed_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'integer',
            'shipping_total' => 'integer',
            'discount_total' => 'integer',
            'grand_total' => 'integer',
            'placed_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function vendorOrders(): HasMany
    {
        return $this->hasMany(VendorOrder::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function voucherRedemptions(): HasMany
    {
        return $this->hasMany(VoucherRedemption::class);
    }
}
