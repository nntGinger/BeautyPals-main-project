<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItemAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_item_id',
        'batch_id',
        'qty',
        'status',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }
}
