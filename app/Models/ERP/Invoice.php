<?php

namespace App\Models\ERP;

use App\Enums\InvoiceStatus;
use App\Models\CRM\Customer;
use App\Models\CRM\SalesOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'invoice_number',
        'customer_id',
        'sales_order_id',
        'status',
        'issue_date',
        'due_date',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'total',
        'paid_amount',
        'notes',
        'created_by',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'sent_at' => 'datetime',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function dueAmount(): float
    {
        return max(0, (float) $this->total - (float) $this->paid_amount);
    }

    public function statusLabel(): string
    {
        return InvoiceStatus::from($this->status)?->label() ?? $this->status;
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! $term) {
            return $query;
        }

        return $query->where('invoice_number', 'like', "%{$term}%")
            ->orWhereHas('customer', fn ($q) => $q->where('name', 'like', "%{$term}%"));
    }
}
