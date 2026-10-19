<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SettlementLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'settlement_id',
        'vendor_order_id',
        'gross',
        'commission',
        'refund',
        'net',
    ];

    protected function casts(): array
    {
        return [
            'gross' => 'integer',
            'commission' => 'integer',
            'refund' => 'integer',
            'net' => 'integer',
        ];
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(Settlement::class);
    }

    public function vendorOrder(): BelongsTo
    {
        return $this->belongsTo(VendorOrder::class);
    }
}
