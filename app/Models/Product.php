<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name_ar', 'unit_id', 'purchase_cost'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    public const DENSITY_LABEL_AR = 'الكثافة';

    protected function casts(): array
    {
        return [
            'purchase_cost' => 'decimal:2',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_id');
    }

    public function specifications(): HasMany
    {
        return $this->hasMany(ProductSpecification::class)->orderBy('sort_order')->orderBy('id');
    }

    public function densitySpecification(): ?ProductSpecification
    {
        return $this->specifications->first(
            fn (ProductSpecification $spec) => $spec->label_ar === self::DENSITY_LABEL_AR
        );
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function (Builder $inner) use ($like): void {
            $inner->where('name_ar', 'like', $like)
                ->orWhereHas('unit', function (Builder $unit) use ($like): void {
                    $unit->where('name_ar', 'like', $like)
                        ->orWhere('symbol_ar', 'like', $like);
                })
                ->orWhereHas('specifications', function (Builder $specs) use ($like): void {
                    $specs->where('label_ar', 'like', $like)
                        ->orWhere('value', 'like', $like);
                });
        });
    }
}
