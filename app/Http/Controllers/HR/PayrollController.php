<?php

namespace App\Http\Controllers\HR;

use App\Enums\PayrollStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\HR\RunPayrollRequest;
use App\Http\Requests\HR\UpdatePayrollRequest;
use App\Models\HR\Payroll;
use App\Services\HR\PayrollService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class PayrollController extends Controller
{
    public function __construct(private readonly PayrollService $payrollService)
    {
    }

    public function index(Request $request): View
    {
        $period = $request->get('period') ?? now()->format('Y-m');

        $payrolls = Payroll::with(['employee:id,first_name,last_name'])
            ->period($period)
            ->status($request->get('status'))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('hr.payroll.index', [
            'payrolls' => $payrolls,
            'period' => $period,
            'statuses' => PayrollStatus::list(),
            'activeStatus' => $request->get('status'),
            'summary' => $this->summary($period),
        ]);
    }

    public function create(): View
    {
        return view('hr.payroll.create');
    }

    public function run(RunPayrollRequest $request): RedirectResponse
    {
        try {
            $result = $this->payrollService->generate($request->validated('period'));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('payroll.index', ['period' => $request->validated('period')])
            ->with('success', "تم توليد {$result['created']} راتب، وتم تخطي {$result['skipped']} موظف لديه راتب سابق");
    }

    public function show(Payroll $payroll): View
    {
        $payroll->load(['employee:id,first_name,last_name,position,department_id', 'employee.department:id,name', 'items']);

        return view('hr.payroll.show', ['payroll' => $payroll]);
    }

    public function update(UpdatePayrollRequest $request, Payroll $payroll): RedirectResponse
    {
        try {
            $this->payrollService->updateAmounts(
                $payroll,
                (float) ($request->validated('allowances') ?? 0),
                (float) ($request->validated('deductions') ?? 0),
                (float) ($request->validated('bonus') ?? 0),
                $request->validated('notes')
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'تم تحديث مكونات الراتب');
    }

    public function approve(Payroll $payroll): RedirectResponse
    {
        try {
            $this->payrollService->approve($payroll);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'تم اعتماد الراتب');
    }

    public function pay(Payroll $payroll): RedirectResponse
    {
        try {
            $this->payrollService->pay($payroll);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'تم صرف الراتب');
    }

    public function destroy(Payroll $payroll): RedirectResponse
    {
        try {
            $this->payrollService->destroy($payroll);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('payroll.index')->with('success', 'تم حذف الراتب');
    }

    private function summary(string $period): array
    {
        $base = Payroll::where('period', $period);

        return [
            'count' => (clone $base)->count(),
            'net' => round((clone $base)->sum('net_total'), 2),
            'draft' => (clone $base)->where('status', 'draft')->count(),
            'approved' => (clone $base)->where('status', 'approved')->count(),
            'paid' => (clone $base)->where('status', 'paid')->count(),
        ];
    }
}