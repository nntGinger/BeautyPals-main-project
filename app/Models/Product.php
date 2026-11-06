<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'brand_id',
        'category_id',
        'name',
        'slug',
        'description',
        'status',
        'reject_reason',
        'moderated_by',
        'moderated_at',
    ];

    protected function casts(): array
    {
        return [
            'moderated_at' => 'datetime',
        ];
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('products.status', 'approved')
            ->whereHas('vendor', fn (Builder $vendors) => $vendors->where('status', 'approved'));
    }

    public function scopeSearchKeyword(Builder $query, ?string $keyword): Builder
    {
        $keyword = trim($keyword ?? '');
        if ($keyword === '') {
            return $query;
        }

        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $keyword);
        $pattern = '%'.$escaped.'%';

        return $query->where(function (Builder $sub) use ($pattern): void {
            $sub->whereRaw("products.name LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereRaw("products.description LIKE ? ESCAPE '!'", [$pattern]);
        });
    }

    public function scopeInCategory(Builder $query, ?int $categoryId): Builder
    {
        if ($categoryId === null || $categoryId <= 0) {
            return $query;
        }

        return $query->where(function (Builder $sub) use ($categoryId): void {
            $sub->where('products.category_id', $categoryId)
                ->orWhereHas('category', fn (Builder $cat) => $cat->where('parent_id', $categoryId));
        });
    }

    public function scopeOfBrand(Builder $query, ?int $brandId): Builder
    {
        if ($brandId === null || $brandId <= 0) {
            return $query;
        }

        return $query->where('products.brand_id', $brandId);
    }

    public function scopeWithIngredient(Builder $query, int|string|null $ingredient): Builder
    {
        if ($ingredient === null || trim((string) $ingredient) === '') {
            return $query;
        }

        if (is_int($ingredient) || ctype_digit((string) $ingredient)) {
            $id = (int) $ingredient;

            return $query->whereHas('ingredients', fn (Builder $ing) => $ing->where('ingredients.id', $id));
        }

        $term = trim((string) $ingredient);

        return $query->whereHas('ingredients', fn (Builder $ing) => $ing->search($term));
    }

    public function scopeWithoutIngredient(Builder $query, int|string|null $ingredient): Builder
    {
        if ($ingredient === null || trim((string) $ingredient) === '') {
            return $query;
        }

        if (is_int($ingredient) || ctype_digit((string) $ingredient)) {
            $id = (int) $ingredient;

            return $query->whereDoesntHave('ingredients', fn (Builder $ing) => $ing->where('ingredients.id', $id));
        }

        $term = trim((string) $ingredient);

        return $query->whereDoesntHave('ingredients', fn (Builder $ing) => $ing->search($term));
    }

    public function scopePriceBetween(Builder $query, ?int $minPrice, ?int $maxPrice): Builder
    {
        if ($minPrice === null && $maxPrice === null) {
            return $query;
        }

        return $query->whereHas('skus', function (Builder $skus) use ($minPrice, $maxPrice): void {
            $skus->where('is_active', true);

            if ($minPrice !== null) {
                $skus->where('price', '>=', $minPrice);
            }

            if ($maxPrice !== null) {
                $skus->where('price', '<=', $maxPrice);
            }
        });
    }

    public function scopeSortByOption(Builder $query, ?string $sort): Builder
    {
        return match ($sort) {
            'price_asc' => $query->withMin(['skus as min_sku_price' => fn (Builder $q) => $q->where('is_active', true)], 'price')
                ->orderBy('min_sku_price', 'asc')
                ->orderByDesc('products.id'),
            'price_desc' => $query->withMin(['skus as min_sku_price' => fn (Builder $q) => $q->where('is_active', true)], 'price')
                ->orderBy('min_sku_price', 'desc')
                ->orderByDesc('products.id'),
            'name_asc' => $query->orderBy('products.name', 'asc'),
            default => $query->orderByDesc('products.id'),
        };
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    public function skinTypes(): BelongsToMany
    {
        return $this->belongsToMany(SkinType::class, 'product_skin_type');
    }

    public function skus(): HasMany
    {
        return $this->hasMany(Sku::class)->orderBy('id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort');
    }

    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class, 'product_ingredients')
            ->withPivot('position', 'concentration')
            ->orderByPivot('position');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function wishedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'wishlists');
    }
}
