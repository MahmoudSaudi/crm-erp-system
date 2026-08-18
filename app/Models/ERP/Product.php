<?php

namespace App\Models\ERP;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'sku',
        'barcode',
        'category_id',
        'unit_id',
        'sale_price',
        'purchase_cost',
        'min_stock',
        'image',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sale_price' => 'decimal:2',
            'purchase_cost' => 'decimal:2',
            'min_stock' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function stockItems()
    {
        return $this->hasMany(StockItem::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function totalStockQuantity(): float
    {
        return (float) $this->stockItems()->sum('quantity');
    }

    public function isLowStock(): bool
    {
        $min = (int) $this->min_stock;

        return $min > 0 && $this->totalStockQuantity() <= $min;
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('sku', 'like', "%{$term}%")
                ->orWhere('barcode', 'like', "%{$term}%");
        });
    }
}
