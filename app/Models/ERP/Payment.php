<?php

namespace App\Models\ERP;

use App\Models\CRM\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'customer_id',
        'amount',
        'method',
        'reference',
        'status',
        'paid_at',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! $term) {
            return $query;
        }

        return $query->where('reference', 'like', "%{$term}%")
            ->orWhereHas('customer', fn ($q) => $q->where('name', 'like', "%{$term}%"))
            ->orWhereHas('invoice', fn ($q) => $q->where('invoice_number', 'like', "%{$term}%"));
    }
}
