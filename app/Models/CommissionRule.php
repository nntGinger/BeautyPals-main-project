<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'rate',
        'effective_from',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'effective_from' => 'date',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted()
    {
        static::saving(function (CommissionRule $rule) {
            $rule->category_key = $rule->category_id ?? 0;

            $rateStr = (string) $rule->rate;
            if (!is_numeric($rateStr)
                || bccomp($rateStr, '0', 2) < 0
                || bccomp($rateStr, '100.00', 2) > 0
            ) {
                throw new \App\Exceptions\Commission\InvalidCommissionRateException(
                    "Commission rate [{$rateStr}] is outside valid range [0, 100]."
                );
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
