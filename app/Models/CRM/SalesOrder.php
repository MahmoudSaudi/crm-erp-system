<?php

namespace App\Models\CRM;

use App\Enums\SalesOrderStatus;
use App\Models\ERP\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_number',
        'customer_id',
        'status',
        'order_date',
        'delivery_date',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'shipping_amount',
        'total',
        'notes',
        'created_by',
        'confirmed_at',
        'fulfilled_at',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'delivery_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'shipping_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'confirmed_at' => 'datetime',
            'fulfilled_at' => 'datetime',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(SalesOrderItem::class);
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusLabel(): string
    {
        return SalesOrderStatus::from($this->status)?->label() ?? $this->status;
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! $term) {
            return $query;
        }

        return $query->where('order_number', 'like', "%{$term}%")
            ->orWhereHas('customer', fn ($q) => $q->where('name', 'like', "%{$term}%"));
    }
}
