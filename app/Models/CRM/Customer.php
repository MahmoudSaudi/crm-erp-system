<?php

namespace App\Models\CRM;

use App\Models\ERP\Invoice;
use App\Models\ERP\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'type',
        'company',
        'tax_number',
        'address',
        'city',
        'credit_limit',
        'balance',
        'status',
        'created_from_lead_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'credit_limit' => 'decimal:2',
            'balance' => 'decimal:2',
        ];
    }

    public function createdFromLead()
    {
        return $this->belongsTo(Lead::class, 'created_from_lead_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function opportunities()
    {
        return $this->hasMany(Opportunity::class);
    }

    public function salesOrders()
    {
        return $this->hasMany(SalesOrder::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function activities()
    {
        return $this->morphMany(Activity::class, 'related');
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")
                ->orWhere('company', 'like', "%{$term}%");
        });
    }
}
