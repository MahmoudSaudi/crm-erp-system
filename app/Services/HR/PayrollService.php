<?php

namespace App\Services\HR;

use App\Enums\PayrollStatus;
use App\Models\HR\Employee;
use App\Models\HR\Payroll;
use App\Services\AuditLogger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PayrollService
{
    /**
     * Generates draft payrolls for the given period (format: YYYY-MM)
     * for every active employee that does NOT already have one.
     */
    public function generate(string $period): array
    {
        return DB::transaction(function () use ($period) {
            if (! preg_match('/^\d{4}-\d{2}$/', $period)) {
                throw new RuntimeException('فترة غير صالحة (الصيغة المطلوبة: YYYY-MM)');
            }

            $start = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
            $end = Carbon::createFromFormat('Y-m', $period)->endOfMonth();

            $employees = Employee::active()->get();
            $existing = Payroll::where('period', $period)->pluck('employee_id');

            $created = 0;
            $skipped = 0;

            foreach ($employees as $employee) {
                if ($existing->contains($employee->id)) {
                    $skipped++;
                    continue;
                }

                $payroll = Payroll::create([
                    'employee_id' => $employee->id,
                    'period' => $period,
                    'period_start' => $start->toDateString(),
                    'period_end' => $end->toDateString(),
                    'base_salary' => (float) $employee->base_salary,
                    'allowances' => 0,
                    'deductions' => 0,
                    'bonus' => 0,
                    'net_total' => (float) $employee->base_salary,
                    'status' => PayrollStatus::Draft->value,
                    'created_by' => auth()->id(),
                ]);

                $payroll->items()->create(['type' => 'base', 'label' => 'الراتب الأساسي', 'amount' => (float) $employee->base_salary]);

                AuditLogger::log('generated', $payroll, null, $payroll->fresh()->toArray());
                $created++;
            }

            return ['created' => $created, 'skipped' => $skipped];
        });
    }

    /**
     * Recomputes net total and syncs breakdown items.
     * Only allowed while the payroll is a draft.
     */
    public function updateAmounts(Payroll $payroll, float $allowances, float $deductions, float $bonus, ?string $notes = null): Payroll
    {
        if ($payroll->status !== PayrollStatus::Draft->value) {
            throw new RuntimeException('لا يمكن تعديل مسودة راتب بعد اعتمادها');
        }

        $old = $payroll->only(['allowances', 'deductions', 'bonus', 'net_total', 'notes']);

        $net = round((float) $payroll->base_salary + $allowances + $bonus - $deductions, 2);

        $payroll->update([
            'allowances' => $allowances,
            'deductions' => $deductions,
            'bonus' => $bonus,
            'notes' => $notes,
            'net_total' => $net,
        ]);

        $payroll->items()->where('type', '!=', 'base')->delete();
        if ($allowances > 0) {
            $payroll->items()->create(['type' => 'allowance', 'label' => 'بدلات', 'amount' => $allowances]);
        }
        if ($bonus > 0) {
            $payroll->items()->create(['type' => 'bonus', 'label' => 'مكافأة', 'amount' => $bonus]);
        }
        if ($deductions > 0) {
            $payroll->items()->create(['type' => 'deduction', 'label' => 'خصومات', 'amount' => $deductions]);
        }

        AuditLogger::log('amounts_updated', $payroll, $old, $payroll->fresh()->only(['allowances', 'deductions', 'bonus', 'net_total', 'notes']));

        return $payroll->fresh();
    }

    public function approve(Payroll $payroll): Payroll
    {
        if ($payroll->status !== PayrollStatus::Draft->value) {
            throw new RuntimeException('لا يمكن اعتماد راتب غير مسودة');
        }

        $old = $payroll->only('status');
        $payroll->update(['status' => PayrollStatus::Approved->value]);
        AuditLogger::log('approved', $payroll, $old, ['status' => PayrollStatus::Approved->value]);

        return $payroll->fresh();
    }

    public function pay(Payroll $payroll): Payroll
    {
        if ($payroll->status !== PayrollStatus::Approved->value) {
            throw new RuntimeException('يجب اعتماد الراتب قبل صرفه');
        }

        $old = $payroll->only(['status', 'paid_at']);
        $payroll->update([
            'status' => PayrollStatus::Paid->value,
            'paid_at' => now()->toDateString(),
        ]);
        AuditLogger::log('paid', $payroll, $old, $payroll->fresh()->only(['status', 'paid_at']));

        return $payroll->fresh();
    }

    public function destroy(Payroll $payroll): void
    {
        if ($payroll->status !== PayrollStatus::Draft->value) {
            throw new RuntimeException('لا يمكن حذف راتب معتمد أو مدفوع');
        }

        AuditLogger::log('deleted', $payroll, $payroll->toArray(), null);
        $payroll->delete();
    }
}