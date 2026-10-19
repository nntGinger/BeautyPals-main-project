<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_order_id',
        'sku_id',
        'product_name',
        'sku_code',
        'unit_price',
        'qty',
        'line_total',
        'commission_rate',
        'commission_amount',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'integer',
            'qty' => 'integer',
            'line_total' => 'integer',
            'commission_rate' => 'decimal:2',
            'commission_amount' => 'integer',
        ];
    }

    public function vendorOrder(): BelongsTo
    {
        return $this->belongsTo(VendorOrder::class);
    }

    public function sku(): BelongsTo
    {
        return $this->belongsTo(Sku::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(OrderItemAllocation::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    public function returnItems(): HasMany
    {
        return $this->hasMany(ReturnItem::class);
    }
}
