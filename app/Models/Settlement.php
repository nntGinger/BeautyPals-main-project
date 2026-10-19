<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Settlement extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'period_start',
        'period_end',
        'gross_sales',
        'commission_total',
        'refund_total',
        'adjustment',
        'net_payout',
        'status',
        'finalized_by',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'gross_sales' => 'integer',
            'commission_total' => 'integer',
            'refund_total' => 'integer',
            'adjustment' => 'integer',
            'net_payout' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SettlementLine::class);
    }
}
