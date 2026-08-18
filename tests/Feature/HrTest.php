<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\PayrollStatus;
use App\Models\HR\Attendance;
use App\Models\HR\Department;
use App\Models\HR\Employee;
use App\Models\HR\Payroll;
use App\Models\User;
use App\Services\HR\PayrollService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    private function admin(): User
    {
        return User::where('email', 'admin@crm.test')->firstOrFail();
    }

    private function hr(): User
    {
        return User::where('email', 'hr@crm.test')->firstOrFail();
    }

    private function support(): User
    {
        return User::where('email', 'support@crm.test')->firstOrFail();
    }

    private function makeDepartment(): Department
    {
        return Department::create(['name' => 'قسم اختبار']);
    }

    private function makeEmployee(Department $department, string $salary = '5000'): Employee
    {
        return Employee::create([
            'department_id' => $department->id,
            'first_name' => 'موظف',
            'last_name' => 'اختبار',
            'email' => 'emp'.uniqid().'@hr.test',
            'salary_type' => 'fixed',
            'base_salary' => $salary,
            'hire_date' => now()->toDateString(),
            'is_active' => true,
        ]);
    }

    public function test_admin_can_manage_departments(): void
    {
        $this->actingAs($this->admin())
            ->post(route('departments.store'), ['name' => 'المحاسبة'])
            ->assertRedirect();

        $this->assertDatabaseHas('departments', ['name' => 'المحاسبة']);
    }

    public function test_department_delete_blocked_when_has_employees(): void
    {
        $department = $this->makeDepartment();
        $this->makeEmployee($department);

        $this->actingAs($this->admin())
            ->delete(route('departments.destroy', $department))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('departments', ['id' => $department->id]);
    }

    public function test_hr_can_create_employee(): void
    {
        $department = $this->makeDepartment();

        $this->actingAs($this->hr())
            ->post(route('employees.store'), [
                'first_name' => 'سارة',
                'last_name' => 'عبد الله',
                'department_id' => $department->id,
                'position' => 'محاسب',
                'salary_type' => 'fixed',
                'base_salary' => 6000,
                'hire_date' => now()->toDateString(),
                'is_active' => 1,
            ])
            ->assertRedirect(route('employees.index'));

        $this->assertDatabaseHas('employees', ['first_name' => 'سارة']);
    }

    public function test_employee_requires_names_and_salary(): void
    {
        $this->actingAs($this->hr())
            ->post(route('employees.store'), [])
            ->assertSessionHasErrors(['first_name', 'last_name', 'salary_type', 'base_salary']);
    }

    public function test_attendance_store_creates_and_upserts_unique_row(): void
    {
        $employee = $this->makeEmployee($this->makeDepartment());
        $date = now()->toDateString();

        $this->actingAs($this->hr())
            ->post(route('attendance.store'), ['employee_id' => $employee->id, 'date' => $date, 'status' => 'present'])
            ->assertSessionHas('success');

        $this->assertSame(1, Attendance::count());

        $this->actingAs($this->hr())
            ->post(route('attendance.store'), ['employee_id' => $employee->id, 'date' => $date, 'status' => 'late'])
            ->assertSessionHas('success');

        $this->assertSame(1, Attendance::count());
        $this->assertSame('late', Attendance::first()->status);
    }

    public function test_attendance_computes_work_hours(): void
    {
        $employee = $this->makeEmployee($this->makeDepartment());

        $this->actingAs($this->hr())
            ->post(route('attendance.store'), [
                'employee_id' => $employee->id,
                'date' => now()->toDateString(),
                'status' => 'present',
                'check_in' => '09:00',
                'check_out' => '17:30',
            ])
            ->assertSessionHas('success');

        $this->assertSame('8.50', (string) Attendance::first()->work_hours);
    }

    public function test_payroll_generate_creates_for_active_employees_only_once(): void
    {
        $dept = $this->makeDepartment();
        $this->makeEmployee($dept, '4000');
        $this->makeEmployee($dept, '3000');
        Employee::create([
            'department_id' => $dept->id,
            'first_name' => 'معطل',
            'last_name' => 'موظف',
            'salary_type' => 'fixed',
            'base_salary' => 2000,
            'is_active' => false,
        ]);

        $period = now()->format('Y-m');

        $this->actingAs($this->hr())
            ->post(route('payroll.run'), ['period' => $period])
            ->assertSessionHas('success');

        $this->assertSame(2, Payroll::where('period', $period)->count());

        $this->actingAs($this->hr())
            ->post(route('payroll.run'), ['period' => $period])
            ->assertSessionHas('success');

        $this->assertSame(2, Payroll::where('period', $period)->count());
    }

    public function test_payroll_lifecycle_draft_approve_paid(): void
    {
        $employee = $this->makeEmployee($this->makeDepartment(), '5000');

        app(PayrollService::class)->generate(now()->format('Y-m'));
        $payroll = Payroll::where('employee_id', $employee->id)->firstOrFail();

        $this->assertSame(PayrollStatus::Draft->value, $payroll->status);
        $this->assertSame('5000.00', (string) $payroll->net_total);

        $this->actingAs($this->hr())
            ->post(route('payroll.approve', $payroll))
            ->assertSessionHas('success');
        $this->assertSame(PayrollStatus::Approved->value, $payroll->fresh()->status);

        $this->actingAs($this->hr())
            ->post(route('payroll.pay', $payroll))
            ->assertSessionHas('success');
        $payroll->refresh();
        $this->assertSame(PayrollStatus::Paid->value, $payroll->status);
        $this->assertNotNull($payroll->paid_at);
    }

    public function test_payroll_amounts_update_recomputes_net_and_items(): void
    {
        $employee = $this->makeEmployee($this->makeDepartment(), '5000');
        app(PayrollService::class)->generate(now()->format('Y-m'));
        $payroll = Payroll::where('employee_id', $employee->id)->firstOrFail();

        $this->actingAs($this->hr())
            ->put(route('payroll.update', $payroll), [
                'allowances' => 500,
                'deductions' => 100,
                'bonus' => 200,
                'notes' => 'بدل مواصلات',
            ])
            ->assertSessionHas('success');

        $payroll->refresh();
        $this->assertSame('5600.00', (string) $payroll->net_total);
        $this->assertSame(4, $payroll->items()->count());
    }

    public function test_payroll_pay_requires_approval_and_destroy_draft_only(): void
    {
        $employee = $this->makeEmployee($this->makeDepartment(), '5000');
        app(PayrollService::class)->generate(now()->format('Y-m'));
        $payroll = Payroll::where('employee_id', $employee->id)->firstOrFail();

        $this->actingAs($this->hr())
            ->post(route('payroll.pay', $payroll))
            ->assertSessionHas('error');
        $this->assertSame(PayrollStatus::Draft->value, $payroll->fresh()->status);

        $this->actingAs($this->hr())
            ->post(route('payroll.approve', $payroll))
            ->assertSessionHas('success');

        $this->actingAs($this->hr())
            ->delete(route('payroll.destroy', $payroll))
            ->assertSessionHas('error');
        $this->assertDatabaseHas('payrolls', ['id' => $payroll->id]);
    }

    public function test_support_cannot_access_hr_pages(): void
    {
        $this->actingAs($this->support())
            ->get(route('employees.index'))->assertForbidden();
        $this->actingAs($this->support())
            ->get(route('attendance.index'))->assertForbidden();
        $this->actingAs($this->support())
            ->get(route('payroll.index'))->assertForbidden();
    }

    public function test_pages_render_for_hr_admin(): void
    {
        $dept = $this->makeDepartment();
        $employee = $this->makeEmployee($dept, '5000');
        app(PayrollService::class)->generate(now()->format('Y-m'));
        $payroll = Payroll::where('employee_id', $employee->id)->firstOrFail();

        $this->actingAs($this->hr())
            ->get(route('departments.index'))->assertOk();
        $this->actingAs($this->hr())
            ->get(route('employees.index'))->assertOk();
        $this->actingAs($this->hr())
            ->get(route('attendance.index'))->assertOk();
        $this->actingAs($this->hr())
            ->get(route('payroll.index'))->assertOk();
        $this->actingAs($this->hr())
            ->get(route('payroll.show', $payroll))->assertOk();
    }
}