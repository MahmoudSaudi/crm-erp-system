<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\StoreEmployeeRequest;
use App\Http\Requests\HR\UpdateEmployeeRequest;
use App\Models\HR\Department;
use App\Models\HR\Employee;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function index(Request $request): View
    {
        $employees = Employee::query()
            ->with(['department:id,name'])
            ->search($request->get('search'))
            ->department($request->integer('department_id') ?: null)
            ->filterActive($request->get('is_active'))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('hr.employees.index', [
            'employees' => $employees,
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'activeStatus' => $request->get('is_active'),
        ]);
    }

    public function create(): View
    {
        return view('hr.employees.create', $this->formData());
    }

    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        $employee = Employee::create($request->validated() + ['is_active' => $request->boolean('is_active', true)]);
        AuditLogger::log('created', $employee, null, $employee->toArray());

        return redirect()->route('employees.index')->with('success', 'تم إضافة الموظف بنجاح');
    }

    public function edit(Employee $employee): View
    {
        return view('hr.employees.edit', ['employee' => $employee] + $this->formData());
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $data = $request->validated();
        $old = $employee->only(array_keys($data));
        $employee->update($data + ['is_active' => $request->boolean('is_active', true)]);
        AuditLogger::log('updated', $employee, $old, $employee->fresh()->only(array_keys($data)));

        return redirect()->route('employees.index')->with('success', 'تم تحديث الموظف');
    }

    public function destroy(Employee $employee): RedirectResponse
    {
        if ($employee->payrolls()->exists()) {
            return back()->with('error', 'لا يمكن حذف موظف لديه سجل رواتب');
        }

        AuditLogger::log('deleted', $employee, $employee->toArray(), null);
        $employee->delete();

        return redirect()->route('employees.index')->with('success', 'تم حذف الموظف');
    }

    private function formData(): array
    {
        return [
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'users' => User::orderBy('name')->get(['id', 'name']),
        ];
    }
}