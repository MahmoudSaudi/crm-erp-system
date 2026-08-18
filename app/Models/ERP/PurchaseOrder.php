<?php

namespace App\Models\ERP;

use App\Enums\PurchaseOrderStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_number',
        'supplier_id',
        'status',
        'order_date',
        'expected_date',
        'received_at',
        'total',
        'notes',
        'created_by',
        'warehouse_id',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'expected_date' => 'date',
            'received_at' => 'datetime',
            'total' => 'decimal:2',
        ];
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusLabel(): string
    {
        return PurchaseOrderStatus::from($this->status)?->label() ?? $this->status;
    }

    public function isPartiallyReceived(): bool
    {
        $total = $this->items->sum('quantity');
        $received = $this->items->sum('received_qty');

        return $total > 0 && $received > 0 && $received < $total;
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! $term) {
            return $query;
        }

        return $query->where('order_number', 'like', "%{$term}%")
            ->orWhereHas('supplier', fn ($q) => $q->where('name', 'like', "%{$term}%"));
    }
}