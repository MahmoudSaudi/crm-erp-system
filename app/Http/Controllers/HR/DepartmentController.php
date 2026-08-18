<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\StoreDepartmentRequest;
use App\Http\Requests\HR\UpdateDepartmentRequest;
use App\Models\HR\Department;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function index(Request $request): View
    {
        $departments = Department::withCount('employees')
            ->search($request->get('search'))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('hr.departments.index', ['departments' => $departments]);
    }

    public function create(): View
    {
        return view('hr.departments.create');
    }

    public function store(StoreDepartmentRequest $request): RedirectResponse
    {
        $department = Department::create($request->validated());
        AuditLogger::log('created', $department, null, $department->toArray());

        return redirect()->route('departments.index')->with('success', 'تم إضافة القسم بنجاح');
    }

    public function edit(Department $department): View
    {
        return view('hr.departments.edit', ['department' => $department]);
    }

    public function update(UpdateDepartmentRequest $request, Department $department): RedirectResponse
    {
        $old = $department->only('name');
        $department->update($request->validated());
        AuditLogger::log('updated', $department, $old, $department->fresh()->only('name'));

        return redirect()->route('departments.index')->with('success', 'تم تحديث القسم');
    }

    public function destroy(Department $department): RedirectResponse
    {
        if ($department->employees()->exists()) {
            return back()->with('error', 'لا يمكن حذف قسم لديه موظفون');
        }

        AuditLogger::log('deleted', $department, $department->toArray(), null);
        $department->delete();

        return redirect()->route('departments.index')->with('success', 'تم حذف القسم');
    }
}