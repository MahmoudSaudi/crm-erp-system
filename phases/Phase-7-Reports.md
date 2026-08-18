# المرحلة 7 — التقارير (Phase 7) ✅ مكتملة (جزئية التقارير)

**المدة:** أسبوع (10)
**الهدف:** بناء صفحة التقارير الكاملة — لوحة تقارير (Hub) + 5 تقارير قابلة للتصفية: المبيعات، المخزون، الأرباح، المصروفات، والفواتير المتأخرة — كلها عربية RTL بصلاحية `view_reports` الحالية (admin, sales-manager, accountant, purchasing-officer).

---

## ما تم تنفيذه في هذه المرحلة

### 1) الخدمة (Service)
**ReportService** — تجميع البيانات للتقارير الخمسة (قراءة فقط من النماذج الحالية):
| الدالة | العائد |
|---|---|
| **`salesSummary(?month)`** | `ordersCount`، `revenue` (مجموع الفواتير غير الملغاة)، `collected` (مجموع المدفوعات)، `due` (= revenue − collected)، `expensesTotal`، `chart` (المبيعات/التحصيل/المصروفات شهريًا لآخر 6 أشهر)، `topCustomers` (أعلى 5)، `recentOrders` (آخر 8 طلبات) |
| **`inventorySummary()`** | `productCount`، `stockValue` (الكمية × تكلفة الشراء)، `lowStockCount`، `lowStockItems`، `byWarehouse` (الكمية بالمستودعات)، `latestMovements` (آخر 10 حركات) |
| **`profitSummary(?month)`** | `revenue`، `cogs` (كمية البيع المؤكد/المنفذ × التكلفة)، `grossProfit`، `expenses`، `netProfit` |
| **`expenseSummary(?month)`** | `total`، `byCategory` (حسب التصنيف)، `byMonth` (حسب الشهر) |
| **`overdueInvoices()`** | `invoices` (غير المدفوعة/الملغاة مع `due_date` ماضٍ ومبلغ مستحق > 0، مرتبة بأيام التأخير تنازليًا)، `totalDue` |

**مؤشرات خاصة:** التصفية عبر `?month=YYYY-MM` (فترة شهر) أو بدونها (السنة الحالية)؛ `chDateRange` يشتق الحدود.

---

### 2) لوحة التحكم (Controller) + المسارات (Routes)
**ReportController** مع 6 دوال:
- `index()` → لوحة التقارير (Hub) — روابط للتقارير الخمسة.
- `sales()` / `profit()` / `expenses()` → تستقبل `?month` وتتحقق من صيغته `YYYY-MM`.
- `inventory()` / `overdue()` → بلا تصفية.

المسارات (كاملة تحت `permission:view_reports`):
| المسار | الاسم |
|---|---|
| `GET /reports` | `reports.index` |
| `GET /reports/sales` | `reports.sales` |
| `GET /reports/inventory` | `reports.inventory` |
| `GET /reports/profit` | `reports.profit` |
| `GET /reports/expenses` | `reports.expenses` |
| `GET /reports/overdue` | `reports.overdue` |

استُبدل مسار الـ placeholder القديم (`PlaceholderController`) بهذه المجموعة.

---

### 3) الصفحات (Views) — عربية RTL
- **Hub (index):** بطاقات لـ 5 تقارير بأيقونات وألوان مميزة (Indigo/مبيعات، Emerald/مخزون، Amber/أرباح، Rose/مصروفات، Sky/متأخرة).
- **تقرير المبيعات:** 4 بطاقات KPI (طلبات/إيرادات/محصّل/مستحق) + رسم Chart.js شهري (3 سلاسل) + أعلى العملاء + آخر طلبات البيع.
- **تقرير المخزون:** 3 بطاقات KPI (منتجات/قيمة المخزون/منخفضة) + التوزيع بالمستودعات + جدول المنتجات منخفضة المخزون + آخر حركات المخزون.
- **تقرير الأرباح:** 4 بطاقات (إيرادات/تكلفة/مجمل/صافي) + ملخص بصيغة المعادلة.
- **تقرير المصروفات:** إجمالي + حسب التصنيف + حسب الشهر.
- **الفواتير المتأخرة:** إجمالي المستحق المتأخر + جدول بأيام التأخير.
- كل الجداول تعرض Empty State عند غياب البيانات، وكلها تدعم الوضع الليلي (Dark mode) كما في بقية المشروع.

---

### 4) الاختبارات (6 اختبارات Feature) — إجمالي المشروع 142
**ReportTest (6):**
1. لوحة التقارير وكل الصفحات الخمس تُعرض للمدير (200).
2. صفحات التقارير ممنوعة عن مندوب المبيعات (403).
3. تقرير المبيعات يجمع الإيرادات والمحصل والمستحق (500 − 200 = 300).
4. تقرير المتأخرة يعرض غير المدفوعة فقط (ويمنع ظهور المدفوعة).
5. تقرير المخزون يعرض المنتجات وقيمة المخزون.
6. تقرير الأرباح والمصروفات يقدمان العمل مع تصفية الشهر.

> **ملاحظة على الأرقام:** الإجمالي الفعلي للمشروع بعد المرحلة السابعة (جزئية التقارير) هو **142 اختبارًا** (136 + 6).

---

### 5) Seeder شامل نهائي (FinalDemoDataSeeder)
أُضيف سيدر جديد يعمل بعد `HrDemoDataSeeder` ويزيد واقعية البيانات عبر 6 أشهر:

| القسم | البيانات |
|---|---|
| **الموردون** | 6 موردين فلسطينيين (أسماء/جهات اتصال/ضرائب) — القسم كان فارغًا كليًا |
| **أوامر الشراء** | 12 أمرًا (9 مستلمة تضخ المخزون، 2 مؤكدة، 1 مسودة، 1 ملغي) بأصناف وكميات تشغيلية عبر `PurchaseOrderService` |
| **المبيعات الموسعة** | 16 أمر بيع موزعة مارσ→أغسطس (24 أمرًا إجماليًا) بمدفوعات كاملة/جزئية/غير مدفوعة، والفواتير غير المدفوعة بحالة `sent` (وليس draft) |

**نتائج مباشرة على التقارير:**
- رسم تقرير المبيعات الشهري أصبح يعرض 6 أشهر حقيقية (آخر النتائج: أغسطس 10059، يوليو 16174، يونيو 18450، مايو 468، أبريل 1331، مارس 1415).
- تقرير المتأخرة: 8 فواتير بـ 11166 إجمالي — توزيع واقعي.
- تقرير الأرباح: إيرادات/تكلفة/مصروفات وصافي واقعي.
- القيمة الإجمالية: 6 موردين / 12 أمر شراء / 24 أمر بيع / 19 فاتورة / 11 دفعة / 14 منتجًا / 9 رواتب.

**أخطاء أُصلحت أثناء التنفيذ:**
1. `InventoryDemoDataSeeder`: استيراد `App\Models\User` ناقص (كان `User::` غير معرّف).
2. `SalesDemoDataSeeder`: كان يحدّث حالة الأمر إلى Fulfilled **قبل** إنشاء الفاتورة (فتفشل) — عُكس الترتيب، والفواتير غير المدفوعة أصبحت `sent`، والتقاط `InsufficientStockException`.
3. `ReportService::monthlyBreakdown`: كان النطاق يبدأ من `$months->first()` (الشهر الحالي) فكان الرسم صفريًا لبقية الأشهر — أصبح `->last()` مع `start/end` صحيحين.

**التحقق:** `php artisan migrate:fresh --seed` يعمل بسلاسة، كل التقارير عبر المتصفح 200، والسويت الكامل = **142 اختبارًا ناجحًا**.

---

## كيف تتجرب بنفسك
```bash
php artisan serve
```
سجّل الدخول بـ `admin@crm.test / password` ثم:
1. **التقارير** من القائمة الجانبية → لوحة الـ Hub.
2. افتح **تقرير المبيعات** → جرّب تصفية شهر (`؟month=YYYY-MM`) ثم أزل التصفية.
3. **تقرير المخزون** → راجع التوزيع بالمستودعات وقائمة المنتجات منخفضة المخزون.
4. **تقرير الأرباح** → قارن الإيرادات والتكلفة والمصروفات وصافي الربح.
5. **الفواتير المتأخرة** → راجع الفواتير بالترتيب تنازليًا لأيام التأخير.
6. سجّل دخول بـ `rep@crm.test` (لا يملك `view_reports`) → صفحة التقارير تعيد 403.

---

*آخر تحديث: أغسطس 2026*

---

## ملخص التنفيذ (Phase 7 — Reports Summary)

Phase 7 (Reports sub-part) complete. All tasks executed and verified.

### Implemented:
- **ReportService** (`app/Services/ReportService.php`): 5 private data methods reading from existing models only — sales (orders/revenue/collected/due/chart/top customers), inventory (stock value/low stock/per-warehouse/latest movements), profit (revenue − COGS − expenses), expenses (by category/month), overdue invoices (unpaid past-due sorted by days).
- **ReportController** (`app/Http/Controllers/ReportController.php`): index/sales/inventory/profit/expenses/overdue, with `YYYY-MM` month validation for filtered reports.
- **Routes:** replaced the `PlaceholderController` reports route with 6 `reports.*` routes, all behind `permission:view_reports`. Removed the now-unused `PlaceholderController` import.
- **Views:** `resources/views/reports/{index,sales,inventory,profit,expenses,overdue}.blade.php` — RTL, dark-mode aware, Empty States, Chart.js on sales page.
- **Docs:** this `phases/Phase-7-Reports.md` + updated `docs/PLAN.md`.

### Bug fix during implementation:
- `salesSummary()` `due` was computed from `invoice.dueAmount()` (relies on invoice `paid_amount` being synced). Since the `payments` table is the source of truth for collections, changed to `due = max(0, revenue − collected)` so a 500 invoice with 200 collected reports due = 300.

### Verification:
`php artisan test tests/Feature/ReportTest.php` → **6 passed / 18 assertions**. Full suite = **142 passed** (136 + 6).

**FinalDemoDataSeeder (comprehensive):** Added suppliers (6), purchase orders (12, 9 received feeding stock), and 16 extended sales orders spread across the last 6 months (media-fly). The monthly sales chart now shows real 6-month data, overdue invoices report 8 unpaid/sent invoices (~11,167 total due). Fixed along the way: missing `User` import in `InventoryDemoDataSeeder`, invoice-before-fulfill ordering + sent status in `SalesDemoDataSeeder`, and the `monthlyBreakdown` range window in `ReportService` (`first()` vs `last()`). Totals: 6 suppliers / 12 POs / 24 sales orders / 19 invoices / 11 payments.

**Note:** this isn't a git repo, so no branch/worktree finishing step applies. Manual smoke test via `php artisan serve` at `admin@crm.test / password`.

Remaining Phase 7 items (planned next): RTL/Empty-State polish, comprehensive final Seeder, README + Case Study, portfolio screenshots.

---

## ✅ Final polish (rescued remaining Phase 7 items)

All remaining Phase 7 items are now complete:

- **RTL + Empty-State + unified colors:** New reusable `<x-empty-state>` Blade component (icon/title/description/CTA) applied across the index views; new-customer button changed from `emerald` to `indigo` for consistency. Live smoke test: all 26 module/report pages return HTTP 200 and pages with no rows render the empty state.
- **README.md:** Rewritten from Laravel's default template into a real project doc (Arabic/English): features, tech stack, setup (`composer install`, `php artisan migrate:fresh --seed`, demo accounts, test commands).
- **docs/CASE_STUDY.md:** Full 10-section case study (summary, problem, challenges & solutions, architecture, numbers, demo data, testing, design, RTL/dark, future roadmap).
- **Screenshots:** 22 new captures in `public/screenshots/` (login, dashboard, all CRM + ERP + HR + support modules, all 6 reports, notifications) — plus the 5 dark-mode shots from an earlier session (27 total).

---

## ملخص التنفيذ بالعربية

اكتملت جزئية التقارير من المرحلة السابعة — نُفّذت وتم التحقق منها.

### ما تم تنفيذه:
- **ReportService:** 5 دوال تجميع (مبيعات/مخزون/أرباح/مصروفات/متأخرة) تقرأ من النماذج الحالية دون أي هجرة قاعدة بيانات.
- **ReportController:** 6 دوال مع تحقق من صيغة الشهر `YYYY-MM`.
- **المسارات:** 6 مسارات `reports.*` خلف `permission:view_reports`، واستُبدل الـ placeholder القديم.
- **الصفحات:** 6 صفحات عربية RTL تدعم الوضع الليلي، مع Empty States وChart.js في تقرير المبيعات.
- **اختبارات:** 6 اختبارات Feature (الإجمالي 142).

### إصلاح أثناء التنفيذ:
- حساب `المستحق` في تقرير المبيعات أصبح `الإيرادات − المحصّل` من جدول `payments` (مصدر الحقيقة للتحصيل) بدل الاعتماد على `paid_amount` في الفاتورة.

### التحقق:
`php artisan test tests/Feature/ReportTest.php` → 6 ناجحة. السويت الكامل = **142 اختبارًا ناجحًا**.

**ملاحظة:** المشروع ليس مستودع Git. اختبار الـ Smoke اليدوي عبر `php artisan serve` بـ `admin@crm.test / password`.

**اللمسات النهائية اكتملت بالكامل:** تحسين RTL مع مكوّن `x-empty-state` موحّد على صفحات الفهارس، Seeder شامل (FinalDemoDataSeeder)، README كامل وكافي، دراسة حالة CASE_STUDY، و22 سكرين شوتس في `public/screenshots/`. التحقق: 142 اختبارًا ناجحًا وجميع الصفحات (26) ترجع HTTP 200.