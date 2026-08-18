<?php

namespace App\Models\HR;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'department_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'national_id',
        'position',
        'salary_type',
        'base_salary',
        'hire_date',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'base_salary' => 'decimal:2',
            'hire_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function payrolls()
    {
        return $this->hasMany(Payroll::class);
    }

    public function attendance()
    {
        return $this->hasMany(Attendance::class);
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDepartment($query, ?int $departmentId)
    {
        if (! $departmentId) {
            return $query;
        }

        return $query->where('department_id', $departmentId);
    }

    public function scopeFilterActive($query, $isActive = null)
    {
        if ($isActive === null || $isActive === '') {
            return $query;
        }

        return $query->where('is_active', (bool) $isActive);
    }

    public function scopeSearch($query, ?string $term)
    {
        if (empty($term)) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%");
        });
    }
}