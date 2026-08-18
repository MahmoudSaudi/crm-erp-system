<?php

namespace App\Models\HR;

use App\Enums\AttendanceStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    protected $table = 'attendance';

    protected $fillable = [
        'employee_id',
        'date',
        'check_in',
        'check_out',
        'work_hours',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'check_in' => 'datetime:H:i',
            'check_out' => 'datetime:H:i',
            'work_hours' => 'decimal:2',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function statusLabel(): string
    {
        return AttendanceStatus::from($this->status)?->label() ?? $this->status;
    }

    public function statusColor(): string
    {
        return AttendanceStatus::from($this->status)?->color() ?? '';
    }

    public function scopeMonth($query, ?string $month)
    {
        if (! $month) {
            return $query;
        }

        return $query->whereYear('date', substr($month, 0, 4))->whereMonth('date', (int) substr($month, 5, 2));
    }

    public function scopeEmployeeId($query, ?int $employeeId)
    {
        if (! $employeeId) {
            return $query;
        }

        return $query->where('employee_id', $employeeId);
    }

    public function scopeStatus($query, ?string $status)
    {
        if (! $status) {
            return $query;
        }

        return $query->where('status', $status);
    }

    /**
     * Upserts a single attendance row honoring unique (employee_id, date).
     */
    public static function upsertRecord(int $employeeId, string $date, array $data): self
    {
        $data['work_hours'] = self::computeWorkHours($data['check_in'] ?? null, $data['check_out'] ?? null);

        return self::updateOrCreate(
            ['employee_id' => $employeeId, 'date' => $date],
            $data
        );
    }

    public function computeWorkHoursPublic(?string $in, ?string $out): float
    {
        return self::computeWorkHours($in, $out);
    }

    private static function computeWorkHours(?string $in, ?string $out): float
    {
        if (! $in || ! $out) {
            return 0;
        }

        $start = Carbon::createFromFormat('H:i', $in);
        $end = Carbon::createFromFormat('H:i', $out);

        return round(max(0, (float) $end->diffInMinutes($start, true) / 60), 2);
    }
}