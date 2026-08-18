<?php

namespace App\Models\ERP;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'quantity',
        'received_qty',
        'unit_cost',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'received_qty' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function remaining(): float
    {
        return (float) $this->quantity - (float) $this->received_qty;
    }

    public function isFullyReceived(): bool
    {
        return $this->remaining() <= 0;
    }
}