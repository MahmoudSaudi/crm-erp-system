<?php

namespace App\Models\CRM;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ticket extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ticket_number',
        'customer_id',
        'subject',
        'message',
        'category',
        'priority',
        'status',
        'assigned_to',
        'created_by',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function messages()
    {
        return $this->hasMany(TicketMessage::class);
    }

    public function statusLabel(): string
    {
        return \App\Enums\TicketStatus::from($this->status)?->label() ?? $this->status;
    }

    public function priorityLabel(): string
    {
        return \App\Enums\TicketPriority::from($this->priority)?->label() ?? $this->priority;
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('ticket_number', 'like', "%{$term}%")
                ->orWhere('subject', 'like', "%{$term}%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$term}%"));
        });
    }
}
