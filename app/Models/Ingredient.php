<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ingredient extends Model
{
    use HasFactory;

    protected $fillable = [
        'inci_name',
        'display_name',
        'function',
    ];

    public function scopeSearch(Builder $query, ?string $keyword): Builder
    {
        $keyword = trim($keyword ?? '');
        if ($keyword === '') {
            return $query;
        }

        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $keyword).'%';

        return $query->where(function (Builder $query) use ($pattern): void {
            $query->whereRaw("inci_name LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereRaw("display_name LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereHas('aliases', fn (Builder $aliases) => $aliases->whereRaw("alias LIKE ? ESCAPE '!'", [$pattern]));
        });
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(IngredientAlias::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_ingredients')
            ->withPivot('position', 'concentration')
            ->orderByPivot('position');
    }
}
