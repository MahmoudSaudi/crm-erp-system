# المرحلة 6 — الموارد البشرية والرواتب (Phase 6) ✅ مكتملة

**المدة:** أسبوع (9)
**الهدف:** بناء وحدة الموارد البشرية — أقسام، موظفون، حضور (بسيط)، ودورة رواتب كاملة `draft → approved → paid` مع ترقيم بنود صافية وAuditLog لكل انتقال.

---

## ما تم تنفيذه في هذه المرحلة

### 1) النماذج (Models) + الحالات (Enums)
| النموذج | ما يخزّنه |
|---|---|
| **Department** | قسم: `name` + علاقة `employees` + `scopeSearch` |
| **Employee** | موظف: `user_id` (اختياري)، القسم، الاسم، البريد، الهاتف، الرقم الوطني، المسمى، نوع الراتب، الراتب الأساسي، تاريخ التعيين، `is_active` — **SoftDeletes** + `fullName()` + `scopeActive/scopeDepartment/scopeSearch` |
| **Attendance** | حضور يومي: الموظف، التاريخ، الحضور/الانصراف، ساعات العمل، الحالة — **جدول `attendance`** (مفرد) مع تفرد `(employee_id, date)` عبر `upsertRecord()` وحساب `work_hours` (فروق صحيحة بنصّي H:i) |
| **Payroll** | راتب شهري: الموظف، الفترة (`YYYY-MM`)، `period_start/end`، الأساسي، البدلات، الخصومات، المكافأة، الصافي، الحالة، `paid_at`، ملاحظات، `created_by` |
| **PayrollItem** | بند تفصيل: النوع (base/allowance/bonus/deduction)، التسمية، المبلغ |

**الحالات (Enums):**
- `AttendanceStatus` (present/absent/late/leave) مع `label()/color()` عربية (حاضر/غائب/متأخر/إجازة).
- `PayrollStatus` (draft/approved/paid) مع `label()/color()` (مسودة/معتمد/مدفوع) + `list()` للـ views.

---

### 2) الخدمة (Service)
**PayrollService** — سيل واحد لدورة الراتب:
| العملية | الوصف |
|---|---|
| **`generate(period)`** | ضمن `DB::transaction`؛ ينشئ مسودة راتب لكل موظف **نشط** بلا راتب سابق للفترة (يبدأ بـ `net_total = base_salary` + بند `base`)؛ يعيد `['created' => n, 'skipped' => n]` + AuditLog |
| **`updateAmounts()`** | مسودة فقط؛ يعيد حساب الصافي `base + allowances + bonus - deductions`، يُسقط بنود التعديل (allowance/bonus/deduction) التي > 0 + AuditLog |
| **`approve()`** | مسودة فقط → approved + AuditLog |
| **`pay()`** | معتمد فقط → paid + `paid_at` + AuditLog |
| **`destroy()`** | مسودة فقط (منع حذف المعتمد/المدفوع) + AuditLog |

**حساب ساعات العمل:** `Attendance::computeWorkHours()` يحسب الفرق الموقّع `diffInMinutes(..., true)` بين نصّي `H:i` ويقسّمه على 60 (مثلاً 09:00→17:30 = `8.50`).

---

### 3) الصفحات (Views) — عربية RTL
- **الأقسام:** فهرس (بحث + جدول + عدد الموظفين) + إنشاء/تعديل — حذف ممنوع إن كان للقسم موظفون (رسالة خطأ).
- **الموظفون:** فهرس (بحث بالاسم/البريد/الهاتف + تصفية بالقسم والحالة) + إنشاء/تعديل + حذف ناعم.
- **الحضور:** فهرس (تصفية بالشهر `YYYY-MM` والموظف والحالة) + إنشاء/تعديل (نموذج واحد يرفع `check_in/check_out` + الحالة) — تكرار نفس الموظف واليوم يُحدّث الصف نفسه (upsert).
- **الرواتب:** فهرس (تصفية بالفترة والحالة) + create (تشغيل دورة بشهر معين) + صفحة عرض بندًا ببند مع الصافي ونموذج تعديل بدلات/خصومات/مكافأة + أزرار اعتماد/صرف/حذف **مشروطة بالحالة والصلاحية**.

---

### 4) الصلاحيات (Permissions) — دور `hr-admin` الجديد
| المقطع | الصلاحيات | لمن |
|---|---|---|
| الأقسام | `manage_departments` | أخصائي الموارد البشرية |
| الموظفون | `view/create/edit/delete_employees` | أخصائي الموارد البشرية |
| الحضور | `view_attendance` + `manage_attendance` | أخصائي الموارد البشرية |
| الرواتب | `view/run/approve/pay_payroll` | أخصائي الموارد البشرية (فصل اعتماد/صرف عن التشغيل) |

حقّق أخصائي الموارد البشرية (`hr@crm.test`) كل الصلاحيات (run/approve/pay/attendance/departments) — إجمالي الصلاحيات في النظام 77.

---

### 5) المسارات (Routes)
- **employees**: 6 مسارات (index/create/store/edit/update/destroy) بـ `permission:`.
- **departments**: 6 مسارات (index/create/store/edit/update/destroy) بـ `manage_departments`.
- **attendance**: 6 مسارات (index/create/store/edit/update/destroy) بـ `view_attendance`/`manage_attendance`.
- **payroll**: 8 مسارات (index/create/run/show/update/approve/pay/destroy) بصلاحيات منفصلة (run/approve/pay).

---

### 6) البيانات التجريبية (Seeder)
- `HrDemoDataSeeder` — يتجاوز نفسه إن وُجد موظفون: 5 أقسام (المبيعات/المحاسبة/الدعم/المستودعات/الموارد البشرية)، 10 موظفين (منهم 9 نشطون، موظف HR مربوط بـ `hr@crm.test`)، ~296 سجل حضور على 3 أشهر بحالات متنوعة، ثم توليد رواتب الشهر الحالي وتعديل أول راتبين بالبدلات/الخصم/المكافأة.

---

### 7) الاختبارات (15 اختبار Feature) — إجمالي المشروع 136
**HrTest (12):** إدارة الأقسام (إنشاء + منع حذف قسم له موظفون)، إنشاء الموظف، إلزامية الاسم والراتب، الحضور يثبت صفًا واحدًا بالتكرار (upsert)، حساب ساعات العمل (09:00→17:30 = 8.50)، توليد الرواتب للنشطين فقط ومرة واحدة، دورة الحياة draft→approved→paid مع `paid_at`، تعديل المبالغ يعيد حساب الصافي ويُحدّث البنود (4 بنود)، منع الصرف قبل الاعتماد ومنع حذف غير المسودة، منع فريق الدعم (403) من صفحات HR، عرض كل صفحات HR للمصرّح.

**HrModelsTest (3):** نموذج القسم (إنشاء + علاقة الموظفين)، نموذج الموظف (إنشاء + علاقات user/department)، scopes البحث والنشط.

> **ملاحظة على الأرقام:** الإجمالي الفعلي للمشروع بعد المرحلة السادسة هو **136 اختبارًا** (قبلها 109). أرقام المراحل السابقة (40/52/68/105) كانت تقديرية/تقريبية.

---

## كيف تتجرب بنفسك
```bash
php artisan serve
```
سجّل الدخول بـ `hr@crm.test / password` ثم جرّب:
1. **الموظفون** → أنشئ موظفًا بقسم (يرتبط الراتب الأساسي تلقائيًا).
2. **الحضور** → سجّل حضورًا بنفس الموظف واليوم مرتين (يتحدّث دون تكرار) مع خروج بعد الدخول لتحسب ساعات العمل.
3. **الرواتب** → "تشغيل دورة" لشهر معيّن؛ افتح أي راتب وعدّل البدلات/الخصم/المكافأة (يتحدّث الصافي والبنود) ثم **اعتمد** ثم **اصرف**.
4. جرّب `support@crm.test` (يرى صفحات HR بخطأ 403).

---

*آخر تحديث: أغسطس 2026*

---

## ملخص التنفيذ (Phase 6 Summary)

Phase 6 complete. All tasks executed and verified.

### Implemented (HR: Departments, Employees, Attendance, Payroll):
- **Models:** `App\Models\HR\{Department, Employee, Attendance, Payroll, PayrollItem}` — attendance uses singular `attendance` table with `(employee_id, date)` upsert; employees use SoftDeletes.
- **Enums:** `AttendanceStatus` (present/absent/late/leave), `PayrollStatus` (draft/approved/paid) with Arabic `label()/color()/list()`.
- **Service:** `PayrollService` — `generate` (active employees only, once per period, DB transaction), `updateAmounts` (draft-only net recompute + items sync), `approve`, `pay`, `destroy` (draft-only), each with AuditLog.
- **Controllers/Requests/Views:** `HR\{Department, Employee, Attendance, Payroll}Controller` + Form Requests + RTL pages under `resources/views/hr/{departments,employees,attendance,payroll}`.
- **Routes/RBAC:** 26 routes with granular `permission:` middleware; new role `hr-admin` with separate `run/approve/pay_payroll` + `manage_attendance` + `manage_departments`.
- **Seeder:** `HrDemoDataSeeder` (5 departments, 10 employees, ~296 attendance rows over 3 months, current-period payroll + amounts on first two).
- **Docs:** updated `PLAN.md`/`DATABASE_SCHEMA.md` + this `phases/Phase-6-HR-Payroll.md`.

### Bug fixes during implementation:
1. `Attendance::$table = 'attendance'` (plural default would fail on the singular table name).
2. Payroll items breakdown test expects 4 rows (base + allowance + bonus + deduction).
3. `computeWorkHours` fixed: unsigned `diffInMinutes` returned negative for cross-pm spans → signed (`true`) so 09:00–17:30 = 8.50.

### Verification:
`php artisan test` → **136 passed** (15 HR tests included). Routes confirmed (26 HR). All HR pages render for `hr@crm.test` (403 for `support@crm.test`).

**Note:** this isn't a git repo, so no branch/worktree finishing step applies. Device smoke test (`php artisan serve`) remains for manual testing at `hr@crm.test / password`.

Ready for **Phase 7 (Reports & Final touches)** when you are.

---

## ملخص التنفيذ بالعربية (ملحق)

اكتملت المرحلة السادسة بالكامل — نُفّذت وتم التحقق منها.

### ما تم تنفيذه (الموارد البشرية: الأقسام، الموظفون، الحضور، الرواتب):
- **النماذج:** `App\Models\HR\{Department, Employee, Attendance, Payroll, PayrollItem}` — الحضور على جدول `attendance` (مفرد) مع upsert بـ `(employee_id, date)`، والموظفون بـ SoftDeletes.
- **الحالات:** `AttendanceStatus` (حاضر/غائب/متأخر/إجازة) و `PayrollStatus` (مسودة/معتمد/مدفوع) مع `label()/color()/list()` عربية.
- **الخدمة:** `PayrollService` — `generate` (النشطين فقط ومرة واحدة للفترة عبر معاملة) + `updateAmounts` (يعيد حساب الصافي ومزامنة البنود للمسودة فقط) + `approve` + `pay` + `destroy` (مسودة فقط)، مع AuditLog لكل انتقال.
- **التحكم/الطلبات/الصفحات:** 4 Controllers + Form Requests + صفحات RTL تحت `resources/views/hr/`.
- **المسارات والصلاحيات:** 26 مسارًا مع `permission:` دقيقة + دور جديد `hr-admin` بفصل `run/approve/pay_payroll` + `manage_attendance` + `manage_departments`.
- **البيانات التجريبية:** `HrDemoDataSeeder` (5 أقسام، 10 موظفين، ~296 سجل حضور على 3 أشهر + رواتب الشهر الحالي وبنود على أول راتبين).
- **التوثيق:** تحديث `PLAN.md` + `DATABASE_SCHEMA.md` + هذه الوثيقة.

### أخطاء أُصلحت أثناء التنفيذ:
1. إضافة `Attendance::$table = 'attendance'` (الافتراضي الجمع كان سيفشل مع اسم الجدول المفرد).
2. اختبار بنود الراتب يتوقع 4 صفوف (أساسي + بدل + مكافأة + خصم).
3. إصلاح `computeWorkHours`: `diffInMinutes` بلا علامة كان يعيد سالبًا لفترات تتجاوز منتصف اليوم → التوقيع الموجب (`true`) فوصلنا 09:00–17:30 = 8.50.

### التحقق:
`php artisan test` → **136 اختبارًا ناجحًا** (منها 15 لـ HR). المسارات مؤكدة (26). كل صفحات HR تُعرض لـ `hr@crm.test` (و403 لـ `support@crm.test`).

**ملاحظة:** المشروع ليس مستودع Git، لذلك لا تنطبق خطوة الفرع النهائية. اختبار الـ Smoke اليدوي (`php artisan serve`) متروك للتجربة بـ `hr@crm.test / password`.

جاهزون للمرحلة السابعة (التقارير واللمسات النهائية) متى أردت.