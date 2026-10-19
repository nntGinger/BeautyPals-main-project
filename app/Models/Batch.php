<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Batch extends Model
{
    use HasFactory;

    protected $fillable = [
        'sku_id',
        'batch_no',
        'mfg_date',
        'exp_date',
        'qty_received',
        'qty_on_hand',
        'qty_reserved',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'mfg_date' => 'date',
            'exp_date' => 'date',
            'qty_received' => 'integer',
            'qty_on_hand' => 'integer',
            'qty_reserved' => 'integer',
        ];
    }

    public function sku(): BelongsTo
    {
        return $this->belongsTo(Sku::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(OrderItemAllocation::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function returnItems(): HasMany
    {
        return $this->hasMany(ReturnItem::class);
    }
}
