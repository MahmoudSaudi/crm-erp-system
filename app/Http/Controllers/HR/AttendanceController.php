<?php

namespace App\Http\Controllers\HR;

use App\Enums\AttendanceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\HR\StoreAttendanceRequest;
use App\Http\Requests\HR\UpdateAttendanceRequest;
use App\Models\HR\Attendance;
use App\Models\HR\Employee;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function index(Request $request): View
    {
        $month = $request->get('month') ?? now()->format('Y-m');

        $records = Attendance::with('employee:id,first_name,last_name')
            ->month($month)
            ->employeeId($request->integer('employee_id') ?: null)
            ->status($request->get('status'))
            ->latest('date')
            ->paginate(20)
            ->withQueryString();

        return view('hr.attendance.index', [
            'records' => $records,
            'month' => $month,
            'employees' => Employee::orderBy('first_name')->get(['id', 'first_name', 'last_name']),
            'statuses' => AttendanceStatus::list(),
            'activeStatus' => $request->get('status'),
            'summary' => $this->summary($month),
        ]);
    }

    public function create(): View
    {
        return view('hr.attendance.create', [
            'employees' => Employee::orderBy('first_name')->get(['id', 'first_name', 'last_name']),
            'statuses' => AttendanceStatus::list(),
        ]);
    }

    public function store(StoreAttendanceRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $record = Attendance::upsertRecord((int) $data['employee_id'], $data['date'], [
            'check_in' => $data['check_in'] ?? null,
            'check_out' => $data['check_out'] ?? null,
            'status' => $data['status'],
        ]);

        AuditLogger::log('created', $record, null, $record->toArray());

        return redirect()->route('attendance.index', ['month' => substr($data['date'], 0, 7)])
            ->with('success', 'تم تسجيل الحضور');
    }

    public function edit(Attendance $attendance): View
    {
        return view('hr.attendance.edit', [
            'attendance' => $attendance,
            'employees' => Employee::orderBy('first_name')->get(['id', 'first_name', 'last_name']),
            'statuses' => AttendanceStatus::list(),
        ]);
    }

    public function update(UpdateAttendanceRequest $request, Attendance $attendance): RedirectResponse
    {
        $data = $request->validated();
        $old = $attendance->only(array_keys($data));
        $attendance->update([
            'check_in' => $data['check_in'] ?? null,
            'check_out' => $data['check_out'] ?? null,
            'status' => $data['status'],
            'date' => $data['date'],
        ]);
        $attendance->update(['work_hours' => $attendance->computeWorkHoursPublic($data['check_in'] ?? null, $data['check_out'] ?? null)]);

        AuditLogger::log('updated', $attendance, $old, $attendance->fresh()->only(array_keys($data) + ['work_hours']));

        return redirect()->route('attendance.index', ['month' => substr($data['date'], 0, 7)])
            ->with('success', 'تم تحديث سجل الحضور');
    }

    public function destroy(Attendance $attendance): RedirectResponse
    {
        AuditLogger::log('deleted', $attendance, $attendance->toArray(), null);
        $attendance->delete();

        return back()->with('success', 'تم حذف سجل الحضور');
    }

    private function summary(string $month): array
    {
        $base = Attendance::whereYear('date', substr($month, 0, 4))->whereMonth('date', (int) substr($month, 5, 2));

        return [
            'total' => (clone $base)->count(),
            'present' => (clone $base)->where('status', 'present')->count(),
            'absent' => (clone $base)->where('status', 'absent')->count(),
            'late' => (clone $base)->where('status', 'late')->count(),
            'leave' => (clone $base)->where('status', 'leave')->count(),
        ];
    }
}