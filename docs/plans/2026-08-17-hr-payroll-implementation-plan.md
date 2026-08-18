# Phase 6: الموارد البشرية (Departments + Employees + Attendance + Payroll)

> **For agentic workers:** نفّذ بالتسلسل مع checkpoint لكل Task. الخطوات بصيغة checkbox.

**Goal:** بناء وحدة الموارد البشرية كاملة — الأقسام، الموظفون، الحضور البسيط، ودورة الرواتب (توليد → اعتماد → صرف).

**Architecture:** Laravel 11 Monolith (Blade/Tailwind/Alpine). منطق الرواتب في `PayrollService` (مالك `DB::transaction`، يمنع تكرار الفترة للموظف، يفرض تسلسل حالات draft→approved→paid). الحضور عبر upsert ذري على قيد `unique(employee_id, date)`. RBAC عبر `RbacSeeder` (صلاحيات HR مربوطة مسبقًا بدور `hr-admin`).

**Tech Stack:** Laravel 11 • Blade • Alpine.js • Tailwind • MySQL (مخطط الموارد البشرية جاهز من Phase 0).

---

## Global Constraints

- لا إضافة حزم جديدة — كل شيء داخل `composer.json` الحالي.
- كل الواجهات عربية RTL بمطابقة أنماط المراحل السابقة (ألوان Indigo، جداول، أزرار).
- الصلاحيات موجودة مسبقًا في `RbacSeeder` — **لا** تُعدَّل إلا عند الحاجة لمطابقة الأسماء.
- Form Requests إلزامية لكل store/update (تماشيًا مع Gap 1).
- حالات الرواتب: `draft → approved → paid` فقط؛ لا يجوز تخطي حالة.
- رفض العمليات بـ `RuntimeException` → `back()->with('error')` في الـ controller (نمط Phase 4/5).
- `net_total = base_salary + allowances + bonus − deductions` وتُعاد دائمًا من خدمة الرواتب.
- الحضور: record واحد لكل `(employee_id, date)` عبر `updateOrCreate`؛ `work_hours` تُحسب من الفرق `check_out − check_in`.
- AuditLogger على كل عمليات الكتابة (created/updated/deleted/approved/paid).

---

## Task 1: الـ Enums والنماذج (Models) الخاصة بالموارد البشرية

**Files:**
- Create: `app/Enums/PayrollStatus.php`
- Create: `app/Enums/AttendanceStatus.php`
- Create: `app/Models/HR/Payroll.php`
- Create: `app/Models/HR/PayrollItem.php`
- Create: `app/Models/HR/Attendance.php`
- Modify: `app/Models/HR/Employee.php` (إضافة `payrolls()` و `attendance()` و `fullName()` و scope `department`)

**Interfaces:**
- Produces:
  - `PayrollStatus` (Draft/Approved/Paid + `label()/color()/list()`).
  - `AttendanceStatus` (Present/Absent/Late/Leave + `label()/color()/list()`).
  - `Payroll` (fillable: `employee_id, period, period_start, period_end, base_salary, allowances, deductions, bonus, net_total, status, paid_at, notes, created_by`) + casts decimal/date + `employee()/items()/totalAdditions()/totalDeductions()`.
  - `PayrollItem` (fillable: `payroll_id, type, label, amount`) + `payroll()`.
  - `Attendance` (fillable: `employee_id, date, check_in, check_out, work_hours, status`) + casts time/date + `employee()` + `scopeMonth` + unique upsert helper.
  - `Employee` relations/helpers.

- [ ] **Step 1: Create `app/Enums/PayrollStatus.php`**

```php
<?php

namespace App\Enums;

enum PayrollStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'مسودة',
            self::Approved => 'معتمد',
            self::Paid => 'مدفوع',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
            self::Approved => 'bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300',
            self::Paid => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300',
        };
    }

    public static function list(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all();
    }
}
```

- [ ] **Step 2: Create `app/Enums/AttendanceStatus.php`**

```php
<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case Present = 'present';
    case Absent = 'absent';
    case Late = 'late';
    case Leave = 'leave';

    public function label(): string
    {
        return match ($this) {
            self::Present => 'حاضر',
            self::Absent => 'غائب',
            self::Late => 'متأخر',
            self::Leave => 'إجازة',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Present => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300',
            self::Absent => 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300',
            self::Late => 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300',
            self::Leave => 'bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300',
        };
    }

    public static function list(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all();
    }
}
```

- [ ] **Step 3: Create `app/Models/HR/Payroll.php`**

```php
<?php

namespace App\Models\HR;

use App\Enums\PayrollStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payroll extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'period',
        'period_start',
        'period_end',
        'base_salary',
        'allowances',
        'deductions',
        'bonus',
        'net_total',
        'status',
        'paid_at',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'paid_at' => 'date',
            'base_salary' => 'decimal:2',
            'allowances' => 'decimal:2',
            'deductions' => 'decimal:2',
            'bonus' => 'decimal:2',
            'net_total' => 'decimal:2',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function items()
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function statusLabel(): string
    {
        return PayrollStatus::from($this->status)?->label() ?? $this->status;
    }

    public function statusColor(): string
    {
        return PayrollStatus::from($this->status)?->color() ?? '';
    }

    public function scopePeriod($query, ?string $period)
    {
        if (! $period) {
            return $query;
        }

        return $query->where('period', $period);
    }

    public function scopeStatus($query, ?string $status)
    {
        if (! $status) {
            return $query;
        }

        return $query->where('status', $status);
    }
}
```

- [ ] **Step 4: Create `app/Models/HR/PayrollItem.php`**

```php
<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'payroll_id',
        'type',
        'label',
        'amount',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function payroll()
    {
        return $this->belongsTo(Payroll::class);
    }
}
```

- [ ] **Step 5: Create `app/Models/HR/Attendance.php`**

```php
<?php

namespace App\Models\HR;

use App\Enums\AttendanceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

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

    private static function computeWorkHours(?string $in, ?string $out): float
    {
        if (! $in || ! $out) {
            return 0;
        }

        $start = \Carbon\Carbon::createFromFormat('H:i', $in);
        $end = \Carbon\Carbon::createFromFormat('H:i', $out);

        return round(max(0, (float) $end->diffInMinutes($start) / 60), 2);
    }
}
```

- [ ] **Step 6: Extend `app/Models/HR/Employee.php`** — add after `department()`:

```php
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

    public function scopeDepartment($query, ?int $departmentId)
    {
        if (! $departmentId) {
            return $query;
        }

        return $query->where('department_id', $departmentId);
    }
```

- [ ] **Step 7: Checkpoint** — `php artisan test tests/Feature/HrModelsTest.php` يبقى ناجحًا (لا اختبارات جديدة بعد).

---

## Task 2: خدمة الرواتب (PayrollService)

**Files:**
- Create: `app/Services/HR/PayrollService.php`

**Interfaces:**
- Consumes: `PayrollStatus`, `Payroll`, `PayrollItem`, `Employee`, `AuditLogger`.
- Produces:
  - `generate(string $period): array` → ينشئ مسودات رواتب لكل الموظفين النشطين الذين **لا** يملكون راتبًا في الفترة؛ يرجع `['created' => count, 'skipped' => count]`.
  - `recalculate(Payroll $payroll): Payroll` → `net_total = base + allowances + bonus − deductions` ويحدّث `items` (type: allowance/bonus/deduction) بالـ labels.
  - `approve(Payroll $payroll): Payroll` → يتطلب draft، ويحوّل إلى `approved`.
  - `pay(Payroll $payroll): Payroll` → يتطلب approved، ويضبط `paid_at = now()`.
  - `destroy(Payroll $payroll): void` → draft فقط.

- [ ] **Step 1: Create `app/Services/HR/PayrollService.php`**

```php
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
```

- [ ] **Step 2: Checkpoint** — لا يشترط اختبار الآن (سيُغطى في Task 8).

---

## Task 3: Form Requests

**Files:**
- Create: `app/Http/Requests/HR/StoreDepartmentRequest.php`
- Create: `app/Http/Requests/HR/UpdateDepartmentRequest.php`
- Create: `app/Http/Requests/HR/StoreEmployeeRequest.php`
- Create: `app/Http/Requests/HR/UpdateEmployeeRequest.php`
- Create: `app/Http/Requests/HR/StoreAttendanceRequest.php`
- Create: `app/Http/Requests/HR/UpdateAttendanceRequest.php`
- Create: `app/Http/Requests/HR/RunPayrollRequest.php`
- Create: `app/Http/Requests/HR/UpdatePayrollRequest.php`

**Interfaces:** كل منها `authorize() => true` + قواعد validation عربية.

- [ ] **Step 1: Create `app/Http/Requests/HR/StoreDepartmentRequest.php`**

```php
<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;

class StoreDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
```

- [ ] **Step 2: Create `app/Http/Requests/HR/UpdateDepartmentRequest.php`** (نفس القواعد + `unique` تجاهل القسم الحالي):

```php
<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:departments,name,'.$this->route('department')->id],
        ];
    }
}
```

- [ ] **Step 3: Create `app/Http/Requests/HR/StoreEmployeeRequest.php`**

```php
<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'user_id' => ['nullable', 'exists:users,id'],
            'national_id' => ['nullable', 'string', 'max:50'],
            'position' => ['nullable', 'string', 'max:255'],
            'salary_type' => ['required', 'in:fixed,commission,hourly'],
            'base_salary' => ['required', 'numeric', 'min:0'],
            'hire_date' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
```

- [ ] **Step 4: Create `app/Http/Requests/HR/UpdateEmployeeRequest.php`** (نفس قواعد Store + تجاهل البريد للموظف الحالي).

- [ ] **Step 5: Create `app/Http/Requests/HR/StoreAttendanceRequest.php`**

```php
<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'exists:employees,id'],
            'date' => ['required', 'date'],
            'status' => ['required', 'in:present,absent,late,leave'],
            'check_in' => ['nullable', 'date_format:H:i'],
            'check_out' => ['nullable', 'date_format:H:i'],
        ];
    }
}
```

- [ ] **Step 6: Create `app/Http/Requests/HR/UpdateAttendanceRequest.php`** (نفس القواعد + تجاهل قيد الفريدة للموظف/التاريخ الحاليين).

- [ ] **Step 7: Create `app/Http/Requests/HR/RunPayrollRequest.php`**

```php
<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;

class RunPayrollRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'period' => ['required', 'regex:/^\d{4}-\d{2}$/'],
        ];
    }
}
```

- [ ] **Step 8: Create `app/Http/Requests/HR/UpdatePayrollRequest.php`**

```php
<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePayrollRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'allowances' => ['nullable', 'numeric', 'min:0'],
            'deductions' => ['nullable', 'numeric', 'min:0'],
            'bonus' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
```

---

## Task 4: وحدة تحكم الشؤون (Department + Employee)

**Files:**
- Create: `app/Http/Controllers/HR/DepartmentController.php`
- Create: `app/Http/Controllers/HR/EmployeeController.php`

**Interfaces:**
- Consumes: Form Requests + models + `AuditLogger`.
- Produces: Department resource (index/create/store/edit/update/destroy) بحماية حذف القسم الذي يحوي موظفين؛ Employee resource مع تصفية قسم/بحث.

- [ ] **Step 1: Create `app/Http/Controllers/HR/DepartmentController.php`**

```php
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
```

- [ ] **Step 2: Add `scopeSearch` to `app/Models/HR/Department.php`**:

```php
    public function scopeSearch($query, ?string $term)
    {
        if (empty($term)) {
            return $query;
        }

        return $query->where('name', 'like', "%{$term}%");
    }
```

- [ ] **Step 3: Create `app/Http/Controllers/HR/EmployeeController.php`**

```php
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
        if ($employee->payrolls()->where('status', '!=', 'cancelled')->exists()) {
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
```

- [ ] **Step 4: Add `filterActive` scope to `app/Models/HR/Employee.php`**:

```php
    public function scopeFilterActive($query, $isActive = null)
    {
        if ($isActive === null || $isActive === '') {
            return $query;
        }

        return $query->where('is_active', (bool) $isActive);
    }
```

---

## Task 5: وحدة التحكم (Attendance + Payroll)

**Files:**
- Create: `app/Http/Controllers/HR/AttendanceController.php`
- Create: `app/Http/Controllers/HR/PayrollController.php`

**Interfaces:**
- Consumes: Form Requests + services + models.
- Produces: Attendance index (filters شهر/موظف/حالة + KPIs) + create/store (upsert) + edit/update/destroy؛ Payroll index (filter period/status + KPIs) + create/run (generate) + show (items + actions) + update (amounts) + approve/pay/destroy.

- [ ] **Step 1: Create `app/Http/Controllers/HR/AttendanceController.php`**

```php
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
```

- [ ] **Step 2: Add public work-hours helper to `app/Models/HR/Attendance.php`** (بجانب upsertRecord):

```php
    public function computeWorkHoursPublic(?string $in, ?string $out): float
    {
        return self::computeWorkHours($in, $out);
    }
```

(alternatively keep the local method; رسّال التعليمة private computeWorkHours مستخدمة داخل الكلاس، والطريقة العامة تستدعيها.)

- [ ] **Step 3: Add `scopeStatus` to `app/Models/HR/Attendance.php`**:

```php
    public function scopeStatus($query, ?string $status)
    {
        if (! $status) {
            return $query;
        }

        return $query->where('status', $status);
    }
```

- [ ] **Step 4: Create `app/Http/Controllers/HR/PayrollController.php`**

```php
<?php

namespace App\Http\Controllers\HR;

use App\Enums\PayrollStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\HR\RunPayrollRequest;
use App\Http\Requests\HR\UpdatePayrollRequest;
use App\Models\HR\Employee;
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

    public function employees(): array
    {
        return Employee::active()->orderBy('first_name')->get(['id', 'first_name', 'last_name'])->toArray();
    }
}
```

---

## Task 6: المسارات (Routes) + الـ Sidebar

**Files:**
- Modify: `routes/web.php` — استبدال مسارات Placeholder الأربعة بمسارات حقيقية.
- Modify: `resources/views/layouts/partials/sidebar.blade.php` — إضافة رابط "الأقسام".

- [ ] **Step 1: Replace the four placeholder HR routes in `routes/web.php`**

```php
use App\Http\Controllers\HR\AttendanceController;
use App\Http\Controllers\HR\DepartmentController;
use App\Http\Controllers\HR\EmployeeController;
use App\Http\Controllers\HR\PayrollController;
```

```php
    Route::get('/departments', [DepartmentController::class, 'index'])->name('departments.index')->middleware('permission:manage_departments');
    Route::get('/departments/create', [DepartmentController::class, 'create'])->name('departments.create')->middleware('permission:manage_departments');
    Route::post('/departments', [DepartmentController::class, 'store'])->name('departments.store')->middleware('permission:manage_departments');
    Route::get('/departments/{department}/edit', [DepartmentController::class, 'edit'])->name('departments.edit')->middleware('permission:manage_departments');
    Route::match(['put', 'patch'], '/departments/{department}', [DepartmentController::class, 'update'])->name('departments.update')->middleware('permission:manage_departments');
    Route::delete('/departments/{department}', [DepartmentController::class, 'destroy'])->name('departments.destroy')->middleware('permission:manage_departments');

    Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index')->middleware('permission:view_employees');
    Route::get('/employees/create', [EmployeeController::class, 'create'])->name('employees.create')->middleware('permission:create_employees');
    Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store')->middleware('permission:create_employees');
    Route::get('/employees/{employee}/edit', [EmployeeController::class, 'edit'])->name('employees.edit')->middleware('permission:edit_employees');
    Route::match(['put', 'patch'], '/employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update')->middleware('permission:edit_employees');
    Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy'])->name('employees.destroy')->middleware('permission:delete_employees');

    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index')->middleware('permission:view_attendance');
    Route::get('/attendance/create', [AttendanceController::class, 'create'])->name('attendance.create')->middleware('permission:manage_attendance');
    Route::post('/attendance', [AttendanceController::class, 'store'])->name('attendance.store')->middleware('permission:manage_attendance');
    Route::get('/attendance/{attendance}/edit', [AttendanceController::class, 'edit'])->name('attendance.edit')->middleware('permission:manage_attendance');
    Route::match(['put', 'patch'], '/attendance/{attendance}', [AttendanceController::class, 'update'])->name('attendance.update')->middleware('permission:manage_attendance');
    Route::delete('/attendance/{attendance}', [AttendanceController::class, 'destroy'])->name('attendance.destroy')->middleware('permission:manage_attendance');

    Route::get('/payroll', [PayrollController::class, 'index'])->name('payroll.index')->middleware('permission:view_payroll');
    Route::get('/payroll/create', [PayrollController::class, 'create'])->name('payroll.create')->middleware('permission:run_payroll');
    Route::post('/payroll/run', [PayrollController::class, 'run'])->name('payroll.run')->middleware('permission:run_payroll');
    Route::get('/payroll/{payroll}', [PayrollController::class, 'show'])->name('payroll.show')->middleware('permission:view_payroll');
    Route::match(['put', 'patch'], '/payroll/{payroll}', [PayrollController::class, 'update'])->name('payroll.update')->middleware('permission:run_payroll');
    Route::post('/payroll/{payroll}/approve', [PayrollController::class, 'approve'])->name('payroll.approve')->middleware('permission:approve_payroll');
    Route::post('/payroll/{payroll}/pay', [PayrollController::class, 'pay'])->name('payroll.pay')->middleware('permission:pay_payroll');
    Route::delete('/payroll/{payroll}', [PayrollController::class, 'destroy'])->name('payroll.destroy')->middleware('permission:run_payroll');
```

- [ ] **Step 2: Add "الأقسام" link to sidebar HR section** (قبل رابط الموظفين):

```blade
                        @can('permission.manage_departments')
                            <x-layout.nav-link :href="route('departments.index')" :active="request()->routeIs('departments.*')" icon="categories">
                                الأقسام
                            </x-layout.nav-link>
                        @endcan
```

---

## Task 7: الـ Views (Blade)

**Files:**
- Create: `resources/views/hr/departments/index.blade.php`
- Create: `resources/views/hr/departments/create.blade.php`
- Create: `resources/views/hr/departments/edit.blade.php`
- Create: `resources/views/hr/employees/index.blade.php`
- Create: `resources/views/hr/employees/_form.blade.php`
- Create: `resources/views/hr/employees/create.blade.php`
- Create: `resources/views/hr/employees/edit.blade.php`
- Create: `resources/views/hr/attendance/index.blade.php`
- Create: `resources/views/hr/attendance/create.blade.php`
- Create: `resources/views/hr/attendance/edit.blade.php`
- Create: `resources/views/hr/payroll/index.blade.php`
- Create: `resources/views/hr/payroll/create.blade.php`
- Create: `resources/views/hr/payroll/show.blade.php`

**نمط موحّد:** `x-app-layout` + عنوان/وصف + جدول مع `@can` للأزرار + فارغ empty-state، مطابق لـ `erp/purchase-orders/index.blade.php`.

- [ ] **Step 1: `hr/departments/index.blade.php`**

```blade
<x-app-layout>
    <x-slot name="title">{{ __('الأقسام') }}</x-slot>

    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('الأقسام') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">إدارة أقسام الشركة</p>
            </div>
            @can('permission.manage_departments')
                <a href="{{ route('departments.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                    {{ __('إضافة قسم') }}
                </a>
            @endcan
        </div>

        <form method="GET" action="{{ route('departments.index') }}" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <x-input-label for="search" value="بحث" />
                    <x-text-input id="search" name="search" type="text" class="mt-1 block w-full" value="{{ request('search') }}" placeholder="اسم القسم..." />
                </div>
            </div>
            <div class="mt-3 flex items-center gap-2">
                <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600">تصفية</button>
                @if (request()->has('search'))
                    <a href="{{ route('departments.index') }}" class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-800">مسح</a>
                @endif
            </div>
        </form>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-800/60">
                        <tr>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">القسم</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">عدد الموظفين</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($departments as $department)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="px-4 py-3 text-sm font-medium text-slate-900 dark:text-white">{{ $department->name }}</td>
                                <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">{{ $department->employees_count }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-1">
                                        @can('permission.manage_departments')
                                            <a href="{{ route('departments.edit', $department) }}" class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-300" title="تعديل">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                            </a>
                                            <form method="POST" action="{{ route('departments.destroy', $department) }}" onsubmit="return confirm('هل تريد حذف هذا القسم؟')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="rounded-md p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10" title="حذف">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-16 text-center text-sm text-slate-500 dark:text-slate-400">لا توجد أقسام بعد</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($departments->hasPages())
                <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-700">{{ $departments->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 2: `hr/departments/create.blade.php` + `hr/departments/edit.blade.php`** (نموذج عادي بلا _form، حقل `name` فقط مع validation).

- [ ] **Step 3: `hr/employees/index.blade.php`** — جدول بأعمدة: الاسم الكامل، القسم، المنصب، الراتب الأساسي، الحالة (نشط/غير نشط)، إجراءات؛ مع فلاتر بحث + قسم + حالة، وأزرار محمية بـ `@can`.

- [ ] **Step 4: `hr/employees/_form.blade.php`** — حقول: first_name/last_name، email، phone، department_id (select)، position، salary_type (select: fixed/commission/hourly عربية)، base_salary (numeric)، hire_date (date)، is_active (checkbox).

- [ ] **Step 5: `hr/employees/create.blade.php` + `edit.blade.php`** — يتضمنان `_form.blade.php`.

- [ ] **Step 6: `hr/attendance/index.blade.php`** — فلاتر month (input type=month) + employee select + status select، بطاقات KPIs (إجمالي/حاضر/غائب/متأخر/إجازة)، جدول السجلات.

- [ ] **Step 7: `hr/attendance/create.blade.php` + `edit.blade.php`** — حقل employee select، date، status select، check_in/check_out (type=time).

- [ ] **Step 8: `hr/payroll/index.blade.php`** — فلاتر period + status، بطاقات KPIs (عدد/صافي/مسودة/معتمد/مدفوع)، جدول بشارات الحالة، زر توليد.

- [ ] **Step 9: `hr/payroll/create.blade.php`** — نموذج `RunPayrollRequest` (حقل period type=month) → route `payroll.run`.

- [ ] **Step 10: `hr/payroll/show.blade.php`** — ملخص الراتب + جدول `payroll->items` + نموذج update amounts (draft فقط) + أزرار approve/pay/destroy محمية بالحالة.

---

## Task 8: بيانات تجريبية (HrDemoDataSeeder)

**Files:**
- Create: `database/seeders/HrDemoDataSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php` (إضافة إلى الـ call list)

- [ ] **Step 1: Create `database/seeders/HrDemoDataSeeder.php`** — منع التكرار عند وجود بيانات HR، ويُنشئ:
  - 5 أقسام (المبيعات، المحاسبة، الدعم، المستودعات، الموارد البشرية).
  - 10 موظفين موزعين على الأقسام (إحداه مرتبط بمستخدم `hr@crm.test`).
  - سجلات حضور للأشهر الثلاثة الماضية لأول 5 موظفين (حاضر/غائب/متأخر/إجازة).
  - راتب تجريبيs لشهر تكوين واحد عبر `PayrollService::generate` ثم تحديث مكونات أول اثنين (reward+خصم لرؤية التغيير).

```php
<?php

namespace Database\Seeders;

use App\Enums\AttendanceStatus;
use App\Models\HR\Attendance;
use App\Models\HR\Department;
use App\Models\HR\Employee;
use App\Models\User;
use Illuminate\Database\Seeder;

class HrDemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (Employee::exists()) {
            return;
        }

        $departments = collect(['المبيعات', 'المحاسبة', 'الدعم', 'المستودعات', 'الموارد البشرية'])
            ->map(fn ($name) => Department::create(['name' => $name]))
            ->keyBy('id');

        $hrUser = User::where('email', 'hr@crm.test')->first();

        $names = [
            ['أحمد', 'الخطيب', 'sales'],
            ['ليلى', 'مراد', 'accounting'],
            ['محمود', 'يوسف', 'support'],
            ['سلمى', 'علي', 'warehouses'],
            ['خالد', 'حسن', 'sales'],
            ['نور', 'صبري', 'accounting'],
            ['عمر', 'الدسوقي', 'support'],
            ['ريم', 'فؤاد', 'warehouses'],
            ['ياسمين', 'قاسم', 'hr'],
            ['طارق', 'منير', 'sales'],
        ];

        $salaryByDept = [
            'sales' => 4000,
            'accounting' => 4500,
            'support' => 3500,
            'warehouses' => 3000,
            'hr' => 5000,
        ];

        $departmentIds = $departments->keys()->all();
        $i = 0;

        foreach ($names as [$first, $last, $deptKey]) {
            $employee = Employee::create([
                'user_id' => ($first === 'ياسمين' && $hrUser) ? $hrUser->id : null,
                'department_id' => $departmentIds[$i % count($departmentIds)],
                'first_name' => $first,
                'last_name' => $last,
                'email' => strtolower($first).'@hr.test',
                'phone' => '0599'.random_int(100000, 999999),
                'position' => $deptKey === 'hr' ? 'اختصاصي موارد بشرية' : 'موظف',
                'salary_type' => 'fixed',
                'base_salary' => $salaryByDept[$deptKey],
                'hire_date' => now()->subMonths(random_int(3, 18))->toDateString(),
                'is_active' => true,
            ]);

            for ($m = 3; $m >= 1; $m--) {
                $month = now()->subMonths($m);
                for ($d = 1; $d <= 22; $d += random_int(1, 2)) {
                    $date = $month->copy()->day(min($d, $month->daysInMonth));
                    if ($date->isWeekend()) {
                        continue;
                    }

                    $status = match (random_int(0, 10)) {
                        0 => AttendanceStatus::Absent->value,
                        1 => AttendanceStatus::Late->value,
                        2 => AttendanceStatus::Leave->value,
                        default => AttendanceStatus::Present->value,
                    };

                    Attendance::upsertRecord($employee->id, $date->toDateString(), [
                        'check_in' => $status === AttendanceStatus::Absent->value || $status === AttendanceStatus::Leave->value ? null : '09:00',
                        'check_out' => $status === AttendanceStatus::Absent->value || $status === AttendanceStatus::Leave->value ? null : '17:00',
                        'status' => $status,
                    ]);
                }
            }

            $i++;
        }

        // Payroll demo: توليد شهر كامل ثم تعديل مكونات أول راتبين
        $service = app(\App\Services\HR\PayrollService::class);
        $service->generate(now()->format('Y-m'));

        $firstTwo = \App\Models\HR\Payroll::where('period', now()->format('Y-m'))->orderBy('id')->limit(2)->get();
        $firstTwo[0]?->update(['allowances' => 500, 'bonus' => 200, 'deductions' => 150]);
        $firstTwo[1]?->update(['allowances' => 300, 'bonus' => 0, 'deductions' => 0]);

        foreach ($firstTwo as $payroll) {
            $payroll->refresh();
            $payroll->items()->where('type', '!=', 'base')->delete();
            if ((float) $payroll->allowances > 0) {
                $payroll->items()->create(['type' => 'allowance', 'label' => 'بدلات', 'amount' => $payroll->allowances]);
            }
            if ((float) $payroll->bonus > 0) {
                $payroll->items()->create(['type' => 'bonus', 'label' => 'مكافأة', 'amount' => $payroll->bonus]);
            }
            if ((float) $payroll->deductions > 0) {
                $payroll->items()->create(['type' => 'deduction', 'label' => 'خصومات', 'amount' => $payroll->deductions]);
            }
            $payroll->update(['net_total' => round((float) $payroll->base_salary + (float) $payroll->allowances + (float) $payroll->bonus - (float) $payroll->deductions, 2)]);
        }
    }
}
```

- [ ] **Step 2: Register in `database/seeders/DatabaseSeeder.php`** (بعد SalesDemoDataSeeder):

```php
            HrDemoDataSeeder::class,
```

---

## Task 9: الاختبارات (Feature Tests)

**Files:**
- Create: `tests/Feature/HrTest.php`

**Interfaces:** تحاكي نمط `PurchaseTest.php` — `RefreshDatabase` + `RbacSeeder`، users `hr@crm.test` (hr-admin) و `support@crm.test` (ممنوع) و `admin@crm.test`.

- [ ] **Step 1: Create `tests/Feature/HrTest.php`** مع الحالات التالية:

```php
<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\PayrollStatus;
use App\Models\HR\Attendance;
use App\Models\HR\Department;
use App\Models\HR\Employee;
use App\Models\HR\Payroll;
use App\Models\User;
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
        $route = route('attendance.store');

        $this->actingAs($this->hr())
            ->post($route, ['employee_id' => $employee->id, 'date' => $date, 'status' => 'present'])
            ->assertSessionHas('success');

        $this->assertSame(1, Attendance::count());

        // تحديث نفس اليوم => upsert بدل تكرار
        $this->actingAs($this->hr())
            ->post($route, ['employee_id' => $employee->id, 'date' => $date, 'status' => 'late'])
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
            'first_name' => 'معطّل',
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

        // تكرار التشغيل => لا يُنشئ مكررات
        $this->actingAs($this->hr())
            ->post(route('payroll.run'), ['period' => $period])
            ->assertSessionHas('success');

        $this->assertSame(2, Payroll::where('period', $period)->count());
    }

    public function test_payroll_lifecycle_draft_approve_paid(): void
    {
        $employee = $this->makeEmployee($this->makeDepartment(), '5000');

        app(\App\Services\HR\PayrollService::class)->generate(now()->format('Y-m'));
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
        app(\App\Services\HR\PayrollService::class)->generate(now()->format('Y-m'));
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
        $this->assertSame('5600.00', (string) $payroll->net_total); // 5000 + 500 + 200 - 100
        $this->assertSame(3, $payroll->items()->count());
    }

    public function test_payroll_approve_cannot_skip_states(): void
    {
        $employee = $this->makeEmployee($this->makeDepartment(), '5000');
        app(\App\Services\HR\PayrollService::class)->generate(now()->format('Y-m'));
        $payroll = Payroll::where('employee_id', $employee->id)->firstOrFail();

        // لا يمكن صرف قبل الاعتماد
        $this->actingAs($this->hr())
            ->post(route('payroll.pay', $payroll))
            ->assertSessionHas('error');
        $this->assertSame(PayrollStatus::Draft->value, $payroll->fresh()->status);

        // لا يمكن حذف بعد الاعتماد
        $this->actingAs($this->hr())
            ->patch(route('payroll.update', $payroll), ['allowances' => 100])
            ->assertSessionHas('success');

        $this->actingAs($this->hr())
            ->post(route('payroll.approve', $payroll))
            ->assertSessionHas('success');

        $this->actingAs($this->hr())
            ->post(route('payroll.update', $payroll), []) // ملاحظة: PUT بدل POST بالطريقة
            ->assertSessionMissing('success');
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
}
```

- [ ] **Step 2: Run — `php artisan test --filter=HrTest` — يجب أن تنجح كل الاختبارات.**

---

## Task 10: التحقق الكامل + التوثيق

- [ ] **Step 1: Run full suite** — `php artisan test` → relies على ألا يتكسر شيء سابقًا.
- [ ] **Step 2: Update `docs/PLAN.md`** — علامة ✅ لمرحلة 6 + ملاحظات التنفيذ.
- [ ] **Step 3: Create `phases/Phase-6-HR-Payroll.md`** — توثيق ما نُفّذ بنمط المراحل السابقة.
- [ ] **Step 4: إعادة تجريب يدوية** — `php artisan db:seed` ثم تصفح صفحات HR لدور `hr@crm.test`.

---
**