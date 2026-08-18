# Phase 7: التقارير (Reports) Implementation Plan

> **For agentic workers:** نفّذ بالتسلسل مع checkpoint لكل Task. الخطوات بصيغة checkbox.

**Goal:** بناء صفحة التقارير الكاملة — لوحة تقارير (Hub) + 5 تقارير قابلة للتصفية: المبيعات، المخزون، الأرباح، المصروفات، والفواتير المتأخرة — كلها عربية RTL بصلاحية `view_reports` الحالية (admin, sales-manager, accountant, purchasing-officer).

**Architecture:** Laravel 11 Monolith (Blade/Tailwind/Alpine/Chart.js). تحويل مسار `reports.index` من `PlaceholderController` إلى `ReportController` حقيقي مع طرق فرعية (index/sales/inventory/profit/expenses/overdue). حسابات التجميع في `ReportService` (نمط خدمات المشروع) يُرجع مصفوفات جاهزة للـ views. النماذج القائمة تُستخدم كما هي (Invoice/Payment/Expense/SalesOrder/SalesOrderItem/Product/StockItem/Warehouse) — لا هجرات DB.

**Tech Stack:** Laravel 11 • Blade • Alpine.js • Tailwind • Chart.js (CDN موجود في layout) • MySQL.

---

## Global Constraints

- لا إضافة حزم جديدة — كل شيء داخل `composer.json` الحالي.
- كل الواجهات عربية RTL بمطابقة أنماط المراحل السابقة (ألوان Indigo، جداول، أزرار، بطاقات `x-dashboard.stat-card`).
- الصلاحيات موجودة مسبقًا في `RbacSeeder` — **لا** تُعدَّل؛ كل مسارات التقارير تحت `permission:view_reports`.
- حسابات مالية تُقرأ من النماذج الحالية فقط (لا مبالغ مُخزّنة جديدة).
- التصفية عبر `request('month')` / `request('year')` بنمط صفحات Phase 5/6 (عروض `?YYYY-MM`).
- Chart.js تُحمَّل مرة واحدة في `layouts/app.blade.php` (موجود) — تُستخدم في صفحات التقارير دون إعادة تحميل.
- كل صفحة تقرير تعرض حالة Empty State عند عدم وجود بيانات.
- AuditLogger غير مطلوب (قراءة فقط).

---

## File Structure

- Create: `app/Services/ReportService.php` — تجميع البيانات للتقارير الخمسة.
- Create: `app/Http/Controllers/ReportController.php` — توجيه الطلبات للـ views.
- Create: `resources/views/reports/index.blade.php` — لوحة التقارير (Hub).
- Create: `resources/views/reports/sales.blade.php`
- Create: `resources/views/reports/inventory.blade.php`
- Create: `resources/views/reports/profit.blade.php`
- Create: `resources/views/reports/expenses.blade.php`
- Create: `resources/views/reports/overdue.blade.php`
- Modify: `routes/web.php` — استبدال مسار placeholder بمسارات ReportController.
- Create: `tests/Feature/ReportTest.php` — اختبارات Feature.
- Create: `docs/phases/`→`phases/Phase-7-Reports.md` — توثيق المرحلة (بعد التنفيذ).

---

### Task 1: ReportService — تجميع بيانات المبيعات والمخزون

**Files:**
- Create: `app/Services/ReportService.php`

**Interfaces:**
- Produces:
  - `ReportService::salesSummary(?string $month): array` — `['ordersCount','revenue','collected','due','chart' => ['labels','sales','payments','expenses'],'topCustomers' => Collection,'recentOrders' => Collection]`.
  - `ReportService::inventorySummary(): array` — `['productCount','stockValue','lowStockCount','lowStockItems' => Collection,'byWarehouse' => Collection,'latestMovements' => Collection]`.

- [ ] **Step 1: Create `app/Services/ReportService.php`**

```php
<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\SalesOrderStatus;
use App\Models\CRM\SalesOrder;
use App\Models\ERP\Expense;
use App\Models\ERP\Invoice;
use App\Models\ERP\Product;
use App\Models\ERP\StockItem;
use App\Models\ERP\StockMovement;
use App\Models\ERP\Warehouse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function salesSummary(?string $month = null): array
    {
        $range = $this->dateRange($month);

        $orders = SalesOrder::with('customer:id,name')
            ->whereNot('status', SalesOrderStatus::Cancelled->value)
            ->whereBetween('order_date', $range)
            ->get();

        $invoices = Invoice::with('customer:id,name')
            ->whereNot('status', InvoiceStatus::Cancelled->value)
            ->whereBetween('issue_date', $range)
            ->get();

        $payments = DB::table('payments')
            ->whereBetween('paid_at', $range)
            ->get();

        $expenses = Expense::whereBetween('date', $range)->get();

        $monthly = $this->monthlyBreakdown($range);

        return [
            'ordersCount' => $orders->count(),
            'revenue' => round((float) $invoices->sum('total'), 2),
            'collected' => round((float) $payments->sum('amount'), 2),
            'due' => round((float) $invoices->sum(fn ($i) => $i->dueAmount()), 2),
            'expensesTotal' => round((float) $expenses->sum('amount'), 2),
            'chart' => $monthly,
            'topCustomers' => $invoices->groupBy('customer_id')
                ->map(fn ($group) => [
                    'name' => $group->first()->customer?->name ?? '—',
                    'total' => round((float) $group->sum('total'), 2),
                ])
                ->sortByDesc('total')
                ->take(5)
                ->values(),
            'recentOrders' => $orders->sortByDesc('order_date')->take(8),
        ];
    }

    public function inventorySummary(): array
    {
        $products = Product::with(['stockItems.warehouse:id,name,code'])->get();

        $lowStock = $products->filter(fn ($p) => $p->isLowStock())->take(10);

        $byWarehouse = Warehouse::with('stockItems')->get()->map(function ($warehouse) {
            return [
                'id' => $warehouse->id,
                'name' => $warehouse->name,
                'code' => $warehouse->code,
                'totalQty' => round((float) $warehouse->stockItems->sum('quantity'), 2),
            ];
        });

        $latestMovements = StockMovement::with(['product:id,name,sku', 'warehouse:id,name,code'])
            ->latest()
            ->limit(10)
            ->get();

        return [
            'productCount' => $products->count(),
            'stockValue' => round((float) $products->sum(fn ($p) => $p->totalStockQuantity() * (float) $p->purchase_cost), 2),
            'lowStockCount' => $products->filter(fn ($p) => $p->isLowStock())->count(),
            'lowStockItems' => $lowStock,
            'byWarehouse' => $byWarehouse,
            'latestMovements' => $latestMovements,
        ];
    }

    private function dateRange(?string $month): array
    {
        if ($month && preg_match('/^\d{4}-\d{2}$/', $month)) {
            $start = \Carbon\Carbon::createFromFormat('Y-m', $month)->startOfMonth();

            return [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()];
        }

        return [now()->startOfYear()->toDateString(), now()->endOfYear()->toDateString()];
    }

    private function monthlyBreakdown(array $range): array
    {
        $months = collect(range(0, 5))->map(fn ($i) => now()->subMonths($i)->startOfMonth());

        $sales = Invoice::whereNot('status', InvoiceStatus::Cancelled->value)
            ->whereBetween('issue_date', [$months->first()->toDateString(), now()->toDateString()])
            ->get()
            ->groupBy(fn ($i) => $i->issue_date->format('Y-m'))
            ->map(fn ($g) => round((float) $g->sum('total'), 2));

        $payments = DB::table('payments')
            ->whereBetween('paid_at', [$months->first()->toDateString(), now()->toDateString()])
            ->get()
            ->groupBy(fn ($p) => substr($p->paid_at, 0, 7))
            ->map(fn ($g) => round((float) $g->sum('amount'), 2));

        $expenses = Expense::whereBetween('date', [$months->first()->toDateString(), now()->toDateString()])
            ->get()
            ->groupBy(fn ($e) => $e->date->format('Y-m'))
            ->map(fn ($g) => round((float) $g->sum('amount'), 2));

        return [
            'labels' => $months->map(fn ($m) => $m->translatedFormat('M Y'))->all(),
            'sales' => $months->map(fn ($m) => (float) ($sales[$m->format('Y-m')] ?? 0))->all(),
            'payments' => $months->map(fn ($m) => (float) ($payments[$m->format('Y-m')] ?? 0))->all(),
            'expenses' => $months->map(fn ($m) => (float) ($expenses[$m->format('Y-m')] ?? 0))->all(),
        ];
    }
}
```

- [ ] **Step 2: Run syntax check**

Run: `php -l app/Services/ReportService.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Smoke-test via tinker**

Run: `php artisan tinker --execute="dump((new App\Services\ReportService)->salesSummary());"`
Expected: مصفوفة ببيانات من قاعدة البيانات الحالية (revenue/ordersCount/chart)، بلا أخطاء.

---

### Task 2: ReportService — الأرباح والمصروفات والفواتير المتأخرة

**Files:**
- Modify: `app/Services/ReportService.php` (إضافة 3 دوال)

**Interfaces:**
- Produces:
  - `ReportService::profitSummary(?string $month): array` — `['revenue','cogs','grossProfit','expenses','netProfit']`.
  - `ReportService::expenseSummary(?string $month): array` — `['total','byCategory' => Collection,'byMonth' => Collection]`.
  - `ReportService::overdueInvoices(): array` — `['invoices' => Collection,'totalDue']`.

- [ ] **Step 1: Add the three methods**

```php
    public function profitSummary(?string $month = null): array
    {
        $range = $this->dateRange($month);

        $invoices = Invoice::whereNot('status', InvoiceStatus::Cancelled->value)
            ->whereBetween('issue_date', $range)
            ->get();

        $revenue = round((float) $invoices->sum('total'), 2);

        $cogs = (float) DB::table('sales_order_items')
            ->join('sales_orders', 'sales_orders.id', '=', 'sales_order_items.sales_order_id')
            ->join('products', 'products.id', '=', 'sales_order_items.product_id')
            ->whereIn('sales_orders.status', [SalesOrderStatus::Confirmed->value, SalesOrderStatus::Fulfilled->value])
            ->whereBetween('sales_orders.order_date', $range)
            ->sum(DB::raw('sales_order_items.quantity * products.purchase_cost'));

        $expenses = round((float) Expense::whereBetween('date', $range)->sum('amount'), 2);

        return [
            'revenue' => $revenue,
            'cogs' => round($cogs, 2),
            'grossProfit' => round($revenue - $cogs, 2),
            'expenses' => $expenses,
            'netProfit' => round($revenue - $cogs - $expenses, 2),
        ];
    }

    public function expenseSummary(?string $month = null): array
    {
        $range = $this->dateRange($month);

        $expenses = Expense::whereBetween('date', $range)->get();

        return [
            'total' => round((float) $expenses->sum('amount'), 2),
            'byCategory' => $expenses->groupBy('category')
                ->map(fn ($g) => [
                    'category' => $g->first()->category,
                    'count' => $g->count(),
                    'total' => round((float) $g->sum('amount'), 2),
                ])
                ->sortByDesc('total')
                ->values(),
            'byMonth' => $expenses->groupBy(fn ($e) => $e->date->format('Y-m'))
                ->map(fn ($g) => [
                    'month' => $g->first()->date->translatedFormat('M Y'),
                    'total' => round((float) $g->sum('amount'), 2),
                ])
                ->sortByDesc(fn ($row) => $row['month'])
                ->values(),
        ];
    }

    public function overdueInvoices(): array
    {
        $invoices = Invoice::with('customer:id,name')
            ->whereNot('status', InvoiceStatus::Paid->value)
            ->whereNot('status', InvoiceStatus::Cancelled->value)
            ->where('due_date', '<', now()->toDateString())
            ->get()
            ->filter(fn ($i) => $i->dueAmount() > 0)
            ->map(function ($invoice) {
                $invoice->days_overdue = (int) now()->startOfDay()->diffInDays($invoice->due_date);

                return $invoice;
            })
            ->sortByDesc('days_overdue')
            ->values();

        return [
            'invoices' => $invoices,
            'totalDue' => round((float) $invoices->sum(fn ($i) => $i->dueAmount()), 2),
        ];
    }
```

- [ ] **Step 2: Run syntax check**

Run: `php -l app/Services/ReportService.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Smoke-test the three methods**

Run: `php artisan tinker --execute="dump((new App\Services\ReportService)->profitSummary()); dump((new App\Services\ReportService)->expenseSummary()); dump((new App\Services\ReportService)->overdueInvoices());"`
Expected: مصفوفات سليمة بلا أخطاء.

---

### Task 3: ReportController + استبدال المسارات

**Files:**
- Create: `app/Http/Controllers/ReportController.php`
- Modify: `routes/web.php` (سطر 205 placeholder)

**Interfaces:**
- Consumes: `ReportService` methods من Task 1-2.
- Produces:
  - `GET /reports` → `reports.index` (Hub)
  - `GET /reports/sales` → `reports.sales`
  - `GET /reports/inventory` → `reports.inventory`
  - `GET /reports/profit` → `reports.profit`
  - `GET /reports/expenses` → `reports.expenses`
  - `GET /reports/overdue` → `reports.overdue`

- [ ] **Step 1: Create `app/Http/Controllers/ReportController.php`**

```php
<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}

    public function index()
    {
        return view('reports.index');
    }

    public function sales(Request $request)
    {
        $month = $this->validMonth($request->get('month'));
        $summary = $this->reports->salesSummary($month);

        return view('reports.sales', compact('month', 'summary'));
    }

    public function inventory()
    {
        $summary = $this->reports->inventorySummary();

        return view('reports.inventory', compact('summary'));
    }

    public function profit(Request $request)
    {
        $month = $this->validMonth($request->get('month'));
        $summary = $this->reports->profitSummary($month);

        return view('reports.profit', compact('month', 'summary'));
    }

    public function expenses(Request $request)
    {
        $month = $this->validMonth($request->get('month'));
        $summary = $this->reports->expenseSummary($month);

        return view('reports.expenses', compact('month', 'summary'));
    }

    public function overdue()
    {
        $summary = $this->reports->overdueInvoices();

        return view('reports.overdue', compact('summary'));
    }

    private function validMonth(?string $month): ?string
    {
        return $month && preg_match('/^\d{4}-\d{2}$/', $month) ? $month : null;
    }
}
```

- [ ] **Step 2: Replace the placeholder route in `routes/web.php`**

استبدال السطر:
```php
Route::get('/reports', [PlaceholderController::class, 'show'])->defaults('module', 'reports')->name('reports.index')->middleware('permission:view_reports');
```
بـ:
```php
Route::prefix('reports')->middleware('permission:view_reports')->group(function () {
    Route::get('/', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/sales', [ReportController::class, 'sales'])->name('reports.sales');
    Route::get('/inventory', [ReportController::class, 'inventory'])->name('reports.inventory');
    Route::get('/profit', [ReportController::class, 'profit'])->name('reports.profit');
    Route::get('/expenses', [ReportController::class, 'expenses'])->name('reports.expenses');
    Route::get('/overdue', [ReportController::class, 'overdue'])->name('reports.overdue');
});
```

- [ ] **Step 3: Add the controller import**

في أعلى `routes/web.php` أضف:
```php
use App\Http\Controllers\ReportController;
```

- [ ] **Step 4: Verify routes**

Run: `php artisan route:list --name=reports`
Expected: 6 مسارات `reports.*` كلها بصلاحية `permission:view_reports`.

---

### Task 4: Views — Hub + Sales Report

**Files:**
- Create: `resources/views/reports/index.blade.php`
- Create: `resources/views/reports/sales.blade.php`

- [ ] **Step 1: Create `resources/views/reports/index.blade.php`**

```blade
<x-app-layout>
    <x-slot name="title">{{ __('التقارير') }}</x-slot>

    <div class="space-y-6">
        <div>
            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('التقارير') }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('نظرة تحليلية على المبيعات والمخزون والأرباح والمصروفات والمستحقات المتأخرة') }}</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <a href="{{ route('reports.sales') }}" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-indigo-300 hover:shadow-md dark:border-slate-700 dark:bg-slate-900 dark:hover:border-indigo-500">
                <div class="flex items-center justify-center w-12 h-12 rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-300">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" /></svg>
                </div>
                <div class="mt-4 text-lg font-bold text-slate-900 dark:text-white">{{ __('تقرير المبيعات') }}</div>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('الإيرادات، التحصيل، المستحق، وأعلى العملاء') }}</p>
            </a>

            <a href="{{ route('reports.inventory') }}" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-emerald-300 hover:shadow-md dark:border-slate-700 dark:bg-slate-900 dark:hover:border-emerald-500">
                <div class="flex items-center justify-center w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-300">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11a4 4 0 11 0 8m0-8a4 4 0 11 0-8m0 8h8m-8 0a4 4 0 000-8m-4 8a4 4 0 11-8 0" /></svg>
                </div>
                <div class="mt-4 text-lg font-bold text-slate-900 dark:text-white">{{ __('تقرير المخزون') }}</div>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('قيمة المخزون، المنتجات منخفضة، والتوزيع بالمستودعات') }}</p>
            </a>

            <a href="{{ route('reports.profit') }}" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-amber-300 hover:shadow-md dark:border-slate-700 dark:bg-slate-900 dark:hover:border-amber-500">
                <div class="flex items-center justify-center w-12 h-12 rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-500/20 dark:text-amber-300">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                </div>
                <div class="mt-4 text-lg font-bold text-slate-900 dark:text-white">{{ __('تقرير الأرباح') }}</div>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('الإيرادات مقابل التكلفة والمصروفات وصافي الربح') }}</p>
            </a>

            <a href="{{ route('reports.expenses') }}" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-rose-300 hover:shadow-md dark:border-slate-700 dark:bg-slate-900 dark:hover:border-rose-500">
                <div class="flex items-center justify-center w-12 h-12 rounded-xl bg-rose-50 text-rose-600 dark:bg-rose-500/20 dark:text-rose-300">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0l3-3m-3 3l-3-3m6 9H6a2 2 0 01-2-2V7a2 2 0 012-2h12a2 2 0 012 2v9a2 2 0 01-2 2z" /></svg>
                </div>
                <div class="mt-4 text-lg font-bold text-slate-900 dark:text-white">{{ __('تقرير المصروفات') }}</div>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('إجمالي المصروفات وتحليلها حسب التصنيف والشهر') }}</p>
            </a>

            <a href="{{ route('reports.overdue') }}" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-sky-300 hover:shadow-md dark:border-slate-700 dark:bg-slate-900 dark:hover:border-sky-500">
                <div class="flex items-center justify-center w-12 h-12 rounded-xl bg-sky-50 text-sky-600 dark:bg-sky-500/20 dark:text-sky-300">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </div>
                <div class="mt-4 text-lg font-bold text-slate-900 dark:text-white">{{ __('الفواتير المتأخرة') }}</div>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('الفواتير غير المدفوعة بعد تاريخ الاستحقاق') }}</p>
            </a>
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 2: Create `resources/views/reports/sales.blade.php`**

```blade
<x-app-layout>
    <x-slot name="title">{{ __('تقرير المبيعات') }}</x-slot>

    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('تقرير المبيعات') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('ملخص المبيعات والتحصيل والمستحق خلال الفترة') }}</p>
            </div>
            <form method="GET" action="{{ route('reports.sales') }}" class="flex items-center gap-2">
                <input type="month" name="month" value="{{ $month }}" class="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900">
                <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">{{ __('تصفية') }}</button>
                @if ($month)
                    <a href="{{ route('reports.sales') }}" class="text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400">{{ __('إزالة التصفية') }}</a>
                @endif
            </form>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('طلبات البيع') }}</p>
                <p class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">{{ number_format($summary['ordersCount']) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('الإيرادات') }}</p>
                <p class="mt-1 text-2xl font-bold text-indigo-600 dark:text-indigo-400">{{ number_format($summary['revenue'], 2) }} ₪</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('المُحصّل') }}</p>
                <p class="mt-1 text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ number_format($summary['collected'], 2) }} ₪</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('المستحق') }}</p>
                <p class="mt-1 text-2xl font-bold text-rose-600 dark:text-rose-400">{{ number_format($summary['due'], 2) }} ₪</p>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">{{ __('المبيعات شهريًا') }}</h3>
                <div class="flex items-center gap-4 text-xs font-medium text-slate-500 dark:text-slate-400">
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-indigo-500"></span> {{ __('المبيعات') }}</span>
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span> {{ __('التحصيل') }}</span>
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span> {{ __('المصروفات') }}</span>
                </div>
            </div>
            <div class="mt-4 h-72">
                <canvas id="salesChart"></canvas>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">{{ __('أعلى العملاء') }}</h3>
                <div class="mt-4 space-y-3">
                    @forelse ($summary['topCustomers'] as $customer)
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ $customer['name'] }}</span>
                            <span class="text-sm font-bold text-indigo-600 dark:text-indigo-400">{{ number_format($customer['total'], 2) }} ₪</span>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('لا توجد بيانات') }}</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">{{ __('آخر طلبات البيع') }}</h3>
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-right text-xs text-slate-500 dark:text-slate-400">
                                <th class="pb-2 font-medium">{{ __('الرقم') }}</th>
                                <th class="pb-2 font-medium">{{ __('العميل') }}</th>
                                <th class="pb-2 font-medium">{{ __('التاريخ') }}</th>
                                <th class="pb-2 font-medium">{{ __('الإجمالي') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @forelse ($summary['recentOrders'] as $order)
                                <tr>
                                    <td class="py-2 font-medium text-slate-900 dark:text-white">{{ $order->order_number }}</td>
                                    <td class="py-2 text-slate-600 dark:text-slate-300">{{ $order->customer?->name ?? '—' }}</td>
                                    <td class="py-2 text-slate-600 dark:text-slate-300">{{ $order->order_date->format('Y-m-d') }}</td>
                                    <td class="py-2 font-bold text-slate-900 dark:text-white">{{ number_format((float) $order->total, 2) }} ₪</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="py-6 text-center text-sm text-slate-500 dark:text-slate-400">{{ __('لا توجد طلبات في هذه الفترة') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof Chart === 'undefined') return;
        const ctx = document.getElementById('salesChart');
        if (!ctx) return;

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: @json($summary['chart']['labels']),
                datasets: [
                    { label: 'المبيعات', data: @json($summary['chart']['sales']), backgroundColor: '#6366f1', borderRadius: 6 },
                    { label: 'التحصيل', data: @json($summary['chart']['payments']), backgroundColor: '#10b981', borderRadius: 6 },
                    { label: 'المصروفات', data: @json($summary['chart']['expenses']), backgroundColor: '#f59e0b', borderRadius: 6 }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        rtl: true,
                        callbacks: { label: (c) => c.dataset.label + ': ' + Number(c.parsed.y).toLocaleString('ar-EG') + ' ₪' }
                    }
                },
                scales: {
                    x: { grid: { display: false }, ticks: { font: { family: 'Cairo' } } },
                    y: { beginAtZero: true }
                }
            }
        });
    });
</script>
```

- [ ] **Step 3: Smoke-test pages**

Run: `php artisan tinker --execute="dump(route('reports.sales'));"`
Expected: `"/reports/sales"` — ثم افتح الصفحات يدويًا بـ `php artisan serve` (login: `admin@crm.test / password`).

---

### Task 5: Views — Inventory + Profit + Expenses + Overdue

**Files:**
- Create: `resources/views/reports/inventory.blade.php`
- Create: `resources/views/reports/profit.blade.php`
- Create: `resources/views/reports/expenses.blade.php`
- Create: `resources/views/reports/overdue.blade.php`

- [ ] **Step 1: Create `resources/views/reports/inventory.blade.php`**

```blade
<x-app-layout>
    <x-slot name="title">{{ __('تقرير المخزون') }}</x-slot>

    <div class="space-y-6">
        <div>
            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('تقرير المخزون') }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('قيمة المخزون والمنتجات منخفضة التوزيع حسب المستودعات') }}</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('عدد المنتجات') }}</p>
                <p class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">{{ number_format($summary['productCount']) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('قيمة المخزون') }}</p>
                <p class="mt-1 text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ number_format($summary['stockValue'], 2) }} ₪</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('منتجات منخفضة') }}</p>
                <p class="mt-1 text-2xl font-bold text-rose-600 dark:text-rose-400">{{ number_format($summary['lowStockCount']) }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">{{ __('التوزيع حسب المستودعات') }}</h3>
                <div class="mt-4 space-y-3">
                    @forelse ($summary['byWarehouse'] as $warehouse)
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ $warehouse['name'] }} <span class="text-xs text-slate-400">({{ $warehouse['code'] }})</span></span>
                            <span class="text-sm font-bold text-slate-900 dark:text-white">{{ number_format($warehouse['totalQty'], 2) }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('لا توجد مستودعات') }}</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">{{ __('منتجات منخفضة المخزون') }}</h3>
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-right text-xs text-slate-500 dark:text-slate-400">
                                <th class="pb-2 font-medium">{{ __('المنتج') }}</th>
                                <th class="pb-2 font-medium">{{ __('الكمية') }}</th>
                                <th class="pb-2 font-medium">{{ __('الحد الأدنى') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @forelse ($summary['lowStockItems'] as $product)
                                <tr>
                                    <td class="py-2 font-medium text-slate-900 dark:text-white">{{ $product->name }}</td>
                                    <td class="py-2 text-rose-600 font-bold dark:text-rose-400">{{ number_format($product->totalStockQuantity(), 2) }}</td>
                                    <td class="py-2 text-slate-600 dark:text-slate-300">{{ number_format((int) $product->min_stock) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="py-6 text-center text-sm text-slate-500 dark:text-slate-400">{{ __('لا توجد منتجات منخفضة') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white">{{ __('آخر حركات المخزون') }}</h3>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-right text-xs text-slate-500 dark:text-slate-400">
                            <th class="pb-2 font-medium">{{ __('التاريخ') }}</th>
                            <th class="pb-2 font-medium">{{ __('المنتج') }}</th>
                            <th class="pb-2 font-medium">{{ __('المستودع') }}</th>
                            <th class="pb-2 font-medium">{{ __('النوع') }}</th>
                            <th class="pb-2 font-medium">{{ __('الكمية') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($summary['latestMovements'] as $movement)
                            <tr>
                                <td class="py-2 text-slate-600 dark:text-slate-300">{{ $movement->created_at->format('Y-m-d H:i') }}</td>
                                <td class="py-2 font-medium text-slate-900 dark:text-white">{{ $movement->product?->name ?? '—' }}</td>
                                <td class="py-2 text-slate-600 dark:text-slate-300">{{ $movement->warehouse?->name ?? '—' }}</td>
                                <td class="py-2"><span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $movement->direction === 'in' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">{{ $movement->direction === 'in' ? 'وارد' : 'صادر' }}</span></td>
                                <td class="py-2 font-bold text-slate-900 dark:text-white">{{ number_format((float) $movement->quantity, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-6 text-center text-sm text-slate-500 dark:text-slate-400">{{ __('لا توجد حركات') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 2: Create `resources/views/reports/profit.blade.php`**

```blade
<x-app-layout>
    <x-slot name="title">{{ __('تقرير الأرباح') }}</x-slot>

    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('تقرير الأرباح') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('الإيرادات مقابل تكلفة البضاعة والمصروفات') }}</p>
            </div>
            <form method="GET" action="{{ route('reports.profit') }}" class="flex items-center gap-2">
                <input type="month" name="month" value="{{ $month }}" class="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900">
                <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">{{ __('تصفية') }}</button>
                @if ($month)
                    <a href="{{ route('reports.profit') }}" class="text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400">{{ __('إزالة التصفية') }}</a>
                @endif
            </form>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('الإيرادات') }}</p>
                <p class="mt-1 text-2xl font-bold text-indigo-600 dark:text-indigo-400">{{ number_format($summary['revenue'], 2) }} ₪</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('تكلفة البضاعة') }}</p>
                <p class="mt-1 text-2xl font-bold text-amber-600 dark:text-amber-400">{{ number_format($summary['cogs'], 2) }} ₪</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('مجمل الربح') }}</p>
                <p class="mt-1 text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ number_format($summary['grossProfit'], 2) }} ₪</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('صافي الربح') }}</p>
                <p class="mt-1 text-2xl font-bold {{ $summary['netProfit'] >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">{{ number_format($summary['netProfit'], 2) }} ₪</p>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white">{{ __('ملخص') }}</h3>
            <dl class="mt-4 space-y-3 text-sm">
                <div class="flex items-center justify-between">
                    <dt class="text-slate-500 dark:text-slate-400">{{ __('المصروفات') }}</dt>
                    <dd class="font-bold text-slate-900 dark:text-white">{{ number_format($summary['expenses'], 2) }} ₪</dd>
                </div>
                <div class="flex items-center justify-between border-t border-slate-100 pt-3 dark:border-slate-800">
                    <dt class="text-slate-500 dark:text-slate-400">{{ __('صافي الربح = الإيرادات − التكلفة − المصروفات') }}</dt>
                    <dd class="font-bold {{ $summary['netProfit'] >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">{{ number_format($summary['netProfit'], 2) }} ₪</dd>
                </div>
            </dl>
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 3: Create `resources/views/reports/expenses.blade.php`**

```blade
<x-app-layout>
    <x-slot name="title">{{ __('تقرير المصروفات') }}</x-slot>

    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('تقرير المصروفات') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('تحليل المصروفات حسب التصنيف والشهر') }}</p>
            </div>
            <form method="GET" action="{{ route('reports.expenses') }}" class="flex items-center gap-2">
                <input type="month" name="month" value="{{ $month }}" class="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900">
                <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">{{ __('تصفية') }}</button>
                @if ($month)
                    <a href="{{ route('reports.expenses') }}" class="text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400">{{ __('إزالة التصفية') }}</a>
                @endif
            </form>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('إجمالي المصروفات') }}</p>
            <p class="mt-1 text-2xl font-bold text-rose-600 dark:text-rose-400">{{ number_format($summary['total'], 2) }} ₪</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">{{ __('حسب التصنيف') }}</h3>
                <div class="mt-4 space-y-3">
                    @forelse ($summary['byCategory'] as $row)
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ $row['category'] }} <span class="text-xs text-slate-400">({{ $row['count'] }})</span></span>
                            <span class="text-sm font-bold text-rose-600 dark:text-rose-400">{{ number_format($row['total'], 2) }} ₪</span>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('لا توجد مصروفات') }}</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">{{ __('حسب الشهر') }}</h3>
                <div class="mt-4 space-y-3">
                    @forelse ($summary['byMonth'] as $row)
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ $row['month'] }}</span>
                            <span class="text-sm font-bold text-slate-900 dark:text-white">{{ number_format($row['total'], 2) }} ₪</span>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('لا توجد مصروفات') }}</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 4: Create `resources/views/reports/overdue.blade.php`**

```blade
<x-app-layout>
    <x-slot name="title">{{ __('الفواتير المتأخرة') }}</x-slot>

    <div class="space-y-6">
        <div>
            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('الفواتير المتأخرة') }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('الفواتير التي فات موعد استحقاقها ولم تُدفع بالكامل') }}</p>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('إجمالي المستحق المتأخر') }}</p>
            <p class="mt-1 text-2xl font-bold text-rose-600 dark:text-rose-400">{{ number_format($summary['totalDue'], 2) }} ₪</p>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-right text-xs text-slate-500 dark:text-slate-400">
                            <th class="pb-2 font-medium">{{ __('رقم الفاتورة') }}</th>
                            <th class="pb-2 font-medium">{{ __('العميل') }}</th>
                            <th class="pb-2 font-medium">{{ __('تاريخ الاستحقاق') }}</th>
                            <th class="pb-2 font-medium">{{ __('أيام التأخير') }}</th>
                            <th class="pb-2 font-medium">{{ __('المستحق') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($summary['invoices'] as $invoice)
                            <tr>
                                <td class="py-2 font-medium text-slate-900 dark:text-white">{{ $invoice->invoice_number }}</td>
                                <td class="py-2 text-slate-600 dark:text-slate-300">{{ $invoice->customer?->name ?? '—' }}</td>
                                <td class="py-2 text-slate-600 dark:text-slate-300">{{ $invoice->due_date->format('Y-m-d') }}</td>
                                <td class="py-2"><span class="rounded-full bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-700 dark:bg-rose-500/20 dark:text-rose-300">{{ $invoice->days_overdue }} يوم</span></td>
                                <td class="py-2 font-bold text-rose-600 dark:text-rose-400">{{ number_format($invoice->dueAmount(), 2) }} ₪</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-6 text-center text-sm text-slate-500 dark:text-slate-400">{{ __('لا توجد فواتير متأخرة — ممتاز!') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 5: Smoke-test pages rendering**

افتح عبر `php artisan serve` (login `admin@crm.test`): `/reports/sales`, `/reports/inventory`, `/reports/profit`, `/reports/expenses`, `/reports/overdue` — كل صفحة تُعرض بنجاح مع بيانات أو Empty State.

---

### Task 6: Feature Tests

**Files:**
- Create: `tests/Feature/ReportTest.php`

- [ ] **Step 1: Create `tests/Feature/ReportTest.php`**

```php
<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\SalesOrderStatus;
use App\Models\CRM\Customer;
use App\Models\CRM\SalesOrder;
use App\Models\CRM\SalesOrderItem;
use App\Models\ERP\Expense;
use App\Models\ERP\Invoice;
use App\Models\ERP\Payment;
use App\Models\ERP\Product;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
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

    private function rep(): User
    {
        return User::where('email', 'rep@crm.test')->firstOrFail();
    }

    public function test_reports_hub_and_all_report_pages_render_for_admin(): void
    {
        $this->actingAs($this->admin())
            ->get(route('reports.index'))->assertOk();
        $this->actingAs($this->admin())
            ->get(route('reports.sales'))->assertOk();
        $this->actingAs($this->admin())
            ->get(route('reports.inventory'))->assertOk();
        $this->actingAs($this->admin())
            ->get(route('reports.profit'))->assertOk();
        $this->actingAs($this->admin())
            ->get(route('reports.expenses'))->assertOk();
        $this->actingAs($this->admin())
            ->get(route('reports.overdue'))->assertOk();
    }

    public function test_report_pages_are_forbidden_for_sales_rep(): void
    {
        $this->actingAs($this->rep())
            ->get(route('reports.index'))->assertForbidden();
        $this->actingAs($this->rep())
            ->get(route('reports.sales'))->assertForbidden();
    }

    public function test_sales_report_aggregates_revenue_and_due(): void
    {
        $customer = Customer::create(['name' => 'عميل', 'phone' => '05990000001']);
        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-T-0001',
            'status' => InvoiceStatus::Sent->value,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'subtotal' => 500,
            'total' => 500,
            'paid_amount' => 0,
        ]);
        Payment::create([
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'amount' => 200,
            'method' => 'cash',
            'status' => 'completed',
            'paid_at' => now(),
        ]);

        $this->actingAs($this->admin())
            ->get(route('reports.sales'))
            ->assertSee('500.00')
            ->assertSee('200.00')
            ->assertSee('300.00');
    }

    public function test_overdue_report_lists_only_unpaid_past_due(): void
    {
        $customer = Customer::create(['name' => 'عميل', 'phone' => '05990000002']);

        Invoice::create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-T-0002',
            'status' => InvoiceStatus::Sent->value,
            'issue_date' => now()->subDays(30)->toDateString(),
            'due_date' => now()->subDays(5)->toDateString(),
            'subtotal' => 300,
            'total' => 300,
            'paid_amount' => 0,
        ]);
        Invoice::create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-T-0003',
            'status' => InvoiceStatus::Paid->value,
            'issue_date' => now()->subDays(30)->toDateString(),
            'due_date' => now()->subDays(5)->toDateString(),
            'subtotal' => 100,
            'total' => 100,
            'paid_amount' => 100,
        ]);

        $this->actingAs($this->admin())
            ->get(route('reports.overdue'))
            ->assertSee('INV-T-0002')
            ->assertSee('300.00')
            ->assertDontSee('INV-T-0003');
    }

    public function test_inventory_report_shows_stock_value_and_low_stock(): void
    {
        $product = Product::create([
            'name' => 'منتج تقرير',
            'sku' => 'RPT-'.uniqid(),
            'sale_price' => 100,
            'purchase_cost' => 50,
            'min_stock' => 5,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin())
            ->get(route('reports.inventory'))
            ->assertOk()
            ->assertSee('منتج تقرير');
    }

    public function test_profit_and_expense_reports_render_with_filters(): void
    {
        $month = now()->format('Y-m');

        $this->actingAs($this->admin())
            ->get(route('reports.profit', ['month' => $month]))->assertOk();
        $this->actingAs($this->admin())
            ->get(route('reports.expenses', ['month' => $month]))->assertOk();
    }
}
```

- [ ] **Step 2: Run the new tests**

Run: `php artisan test tests/Feature/ReportTest.php`
Expected: `PASS` — 6 اختبارات ناجحة.

- [ ] **Step 3: Run the full suite**

Run: `php artisan test`
Expected: كل الاختبارات ناجحة (`136 + 6 = 142`).

---

### Task 7: توثيق المرحلة (Docs)

**Files:**
- Create: `phases/Phase-7-Reports.md`
- Modify: `docs/PLAN.md` (Phase 7: تعليم أول بند تقارير + ملاحظة تنفيذ)

- [ ] **Step 1: Create `phases/Phase-7-Reports.md`** — بمطابقة نمط `phases/Phase-6-HR-Payroll.md` (الهدف/ما تم تنفيذه/الصلاحيات/المسارات/الاختبارات/كيف تتجرب/ملخص عربي+إنجليزي)، مع أرقام صحيحة: 6 مسارات `reports.*`، 6 اختبارات جديدة، الإجمالي **142**.

- [ ] **Step 2: Update `docs/PLAN.md` Phase 7** — اجعل بند «تقارير» معلمًا `[x]` وأضف ملاحظة تنفيذ قصيرة مع تحديث الإجمالي إلى 142.

- [ ] **Step 3: Final check** — `php artisan test` مرة أخيرة وتأكيد الرقم النهائي في الوثائق.

---

## Self-Review

- **Spec coverage:** Phase 7's report scope (المبيعات/المخزون/الأرباح/المصروفات/الفواتير المتأخرة) مغطّى بالكامل في Tasks 1-5؛ الصلاحية `view_reports` قائمة بلا تعديل؛ التصفية بالشهر مدعومة في sales/profit/expenses؛ Empty States في كل الجداول.
- **Placeholder scan:** لا يوجد TBD/TODO؛ كل الخطوات تحمل كودًا كاملًا.
- **Type consistency:** `ReportService` الدوال (salesSummary/inventorySummary/profitSummary/expenseSummary/overdueInvoices) و`ReportController` (index/sales/inventory/profit/expenses/overdue) متطابقة عبر Tasks. مفاتيح المصفوفات (`revenue/collected/due/cogs/netProfit/...`) متطابقة بين الخدمة والـ views.
