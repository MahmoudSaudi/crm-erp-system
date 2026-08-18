<?php

namespace App\Models\CRM;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Opportunity extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'customer_id',
        'lead_id',
        'stage_id',
        'amount',
        'probability',
        'expected_close_date',
        'description',
        'assigned_to',
        'created_by',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'probability' => 'integer',
            'expected_close_date' => 'date',
            'closed_at' => 'datetime',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function stage()
    {
        return $this->belongsTo(OpportunityStage::class, 'stage_id');
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
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
            $q->where('title', 'like', "%{$term}%");
        });
    }

    public function isClosed(): bool
    {
        return $this->stage?->is_won || $this->stage?->is_lost;
    }
}
