<?php

namespace Database\Seeders;

use App\Enums\AttendanceStatus;
use App\Models\HR\Attendance;
use App\Models\HR\Department;
use App\Models\HR\Employee;
use App\Models\HR\Payroll;
use App\Models\User;
use Illuminate\Database\Seeder;

class HrDemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (Employee::exists()) {
            return;
        }

        $departmentNames = ['المبيعات', 'المحاسبة', 'الدعم', 'المستودعات', 'الموارد البشرية'];
        $departments = collect($departmentNames)->mapWithKeys(
            fn ($name) => [$name => Department::create(['name' => $name])]
        );

        $hrUser = User::where('email', 'hr@crm.test')->first();

        $data = [
            ['أحمد', 'الخطيب', 'المبيعات', 'مندوب مبيعات', 4000, true],
            ['ليلى', 'مراد', 'المحاسبة', 'محاسب', 4500, true],
            ['محمود', 'يوسف', 'الدعم', 'اختصاصي دعم', 3500, true],
            ['سلمى', 'علي', 'المستودعات', 'مأمور مخزن', 3000, true],
            ['خالد', 'حسن', 'المبيعات', 'مدير مبيعات', 6000, true],
            ['نور', 'صبري', 'المحاسبة', 'محاسب', 4200, true],
            ['عمر', 'الدسوقي', 'الدعم', 'اختصاصي دعم', 3500, true],
            ['ريم', 'فؤاد', 'المستودعات', 'مأمور مخزن', 3000, true],
            ['ياسمين', 'قاسم', 'الموارد البشرية', 'اختصاصي موارد بشرية', 5000, true],
            ['طارق', 'منير', 'المبيعات', 'مندوب مبيعات', 4000, false],
        ];

        foreach ($data as [$first, $last, $dept, $position, $salary, $active]) {
            $employee = Employee::create([
                'user_id' => ($first === 'ياسمين' && $hrUser) ? $hrUser->id : null,
                'department_id' => $departments[$dept]->id,
                'first_name' => $first,
                'last_name' => $last,
                'email' => strtolower($first).'@hr.test',
                'phone' => '0599'.random_int(100000, 999999),
                'position' => $position,
                'salary_type' => 'fixed',
                'base_salary' => $salary,
                'hire_date' => now()->subMonths(random_int(3, 18))->toDateString(),
                'is_active' => $active,
            ]);

            for ($m = 3; $m >= 1; $m--) {
                $month = now()->subMonths($m);
                for ($d = 1; $d <= 22; $d += random_int(1, 2)) {
                    $date = $month->copy()->day(min($d, $month->daysInMonth));
                    if ($date->isWeekend()) {
                        continue;
                    }

                    $roll = random_int(0, 10);
                    $status = match (true) {
                        $roll === 0 => AttendanceStatus::Absent->value,
                        $roll === 1 => AttendanceStatus::Late->value,
                        $roll === 2 => AttendanceStatus::Leave->value,
                        default => AttendanceStatus::Present->value,
                    };

                    $hasHours = ! in_array($status, [AttendanceStatus::Absent->value, AttendanceStatus::Leave->value], true);

                    Attendance::upsertRecord($employee->id, $date->toDateString(), [
                        'check_in' => $hasHours ? '09:00' : null,
                        'check_out' => $hasHours ? '17:00' : null,
                        'status' => $status,
                    ]);
                }
            }
        }

        $service = app(\App\Services\HR\PayrollService::class);
        $service->generate(now()->format('Y-m'));

        $firstTwo = Payroll::where('period', now()->format('Y-m'))->orderBy('id')->limit(2)->get();
        if ($firstTwo->count() >= 1) {
            $service->updateAmounts($firstTwo[0], 500, 150, 200, 'بدل مواصلات ومكافأة أداء');
        }
        if ($firstTwo->count() >= 2) {
            $service->updateAmounts($firstTwo[1], 300, 0, 0, 'بدل سكن');
        }
    }
}