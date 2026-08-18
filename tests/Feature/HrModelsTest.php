<?php

namespace Tests\Feature;

use App\Models\HR\Department;
use App\Models\HR\Employee;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrModelsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_department_model_creates_and_has_employees(): void
    {
        $department = Department::create(['name' => 'المبيعات']);

        $this->assertDatabaseHas('departments', ['id' => $department->id, 'name' => 'المبيعات']);
        $this->assertCount(0, $department->employees);
    }

    public function test_employee_model_creates_with_relations(): void
    {
        $department = Department::create(['name' => 'المحاسبة']);
        $user = User::where('email', 'admin@crm.test')->firstOrFail();

        $employee = Employee::create([
            'user_id' => $user->id,
            'department_id' => $department->id,
            'first_name' => 'منى',
            'last_name' => 'الفتح',
            'email' => 'mona@hr.test',
            'salary_type' => 'fixed',
            'base_salary' => 5000,
            'hire_date' => now(),
        ]);

        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'first_name' => 'منى']);
        $this->assertSame($department->id, $employee->department->id);
        $this->assertSame($user->id, $employee->user->id);
    }

    public function test_employee_scope_search_and_active(): void
    {
        Employee::create([
            'first_name' => 'سارة',
            'last_name' => 'عبد الله',
            'email' => 'sara@hr.test',
            'base_salary' => 3000,
            'is_active' => true,
        ]);

        Employee::create([
            'first_name' => 'نور',
            'last_name' => 'سليم',
            'email' => 'noor@hr.test',
            'base_salary' => 2500,
            'is_active' => false,
        ]);

        $this->assertCount(1, Employee::search('سارة')->get());
        $this->assertCount(1, Employee::active()->get());
        $this->assertCount(2, Employee::all());
    }
}