<?php

namespace App\Models\HR;

use App\Enums\PayrollStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payroll extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'period',
        'period_start',
        'period_end',
        'base_salary',
        'allowances',
        'deductions',
        'bonus',
        'net_total',
        'status',
        'paid_at',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'paid_at' => 'date',
            'base_salary' => 'decimal:2',
            'allowances' => 'decimal:2',
            'deductions' => 'decimal:2',
            'bonus' => 'decimal:2',
            'net_total' => 'decimal:2',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function items()
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function statusLabel(): string
    {
        return PayrollStatus::from($this->status)?->label() ?? $this->status;
    }

    public function statusColor(): string
    {
        return PayrollStatus::from($this->status)?->color() ?? '';
    }

    public function scopePeriod($query, ?string $period)
    {
        if (! $period) {
            return $query;
        }

        return $query->where('period', $period);
    }

    public function scopeStatus($query, ?string $status)
    {
        if (! $status) {
            return $query;
        }

        return $query->where('status', $status);
    }
}