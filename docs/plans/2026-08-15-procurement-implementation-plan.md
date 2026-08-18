# Phase 4: المشتريات (Suppliers + Purchase Orders) — خطة التنفيذ

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** بناء وحدتي الموردين وأوامر الشراء كاملًا (Models + Service + Controllers + Routes + Views + Validation + Tests) لتحل محل `PlaceholderController`.

**Architecture:** خادم Laravel 11 Monolith (Blade/Tailwind/Alpine). كل منطق الأعمال في `PurchaseOrderService` (مالك `DB::transaction`)، المخزون عبر `StockService::in()` الموجودة، الاستلام الجزئي متعدد المرات، RBAC من `RbacSeeder`.

**Tech Stack:** PHP 8.2 · Laravel 11 · Blade + Tailwind CSS + Alpine.js · MySQL/MariaDB · PHPUnit Feature tests.

**مرجع التصميم:** `docs/specs/2026-08-15-procurement-suppliers-purchase-orders-design.md`

---

## Global Constraints

- كل الكود داخل نمط المشروع الحالي: Models في `App\Models\ERP\`، Controllers في `App\Http\Controllers\ERP\`، Services في `App\Services\ERP\`، Views في `resources/views/erp/...`.
- كل واجهة عربية RTL، رسائل نجاح/خطأ عربية مثل باقي modules.
- كل عملية كتابة تُسجَّل بـ `AuditLogger::log(...)` (نمط `WarehouseController`/`SalesOrderController`).
- الـ Backend هو خط الدفاع الوحيد عن validation و RBAC — الـ UI عرض فقط.
- `supplier_id` و `warehouse_id` **required** في الـ Validation (لكن أعمدة DB تبقى nullable بنمط `nullOnDelete`).
- `receive()` يقرأ المستودع من `$order->warehouse` — لا يُمرَّر مستودع خارجي.
- `DB::transaction()` ملك الـ Service وليس الـ Controller.
- الترقيم: `PO-` + 6 أرقام (نمط `SO-000001`).
- لا توجد مكتبات جديدة؛ لا حالة جديدة غير المحددة؛ لا يعمل في `git` (المجلد غير repo — لا تُنفَّذ أوامر commit).
- التحقق: `php artisan test` (كل شيء أخضر) و `php artisan route:list`.

---

## Task 1: Database / Migration

**Files:**
- Create: `database/migrations/2026_08_15_000001_add_warehouse_id_to_purchase_orders_table.php`

**Interfaces:**
- Produces: عمود `warehouse_id` في جدول `purchase_orders` (FK nullable → `warehouses`, `nullOnDelete`).

- [ ] **Step 1: إنشاء ملف الـ migration الجديد**
  - استخدم صيغة ملف `2026_08_13_000000_make_leads_last_name_nullable.php` كنموذج (migration تعديلي على جدول قائم).

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('created_by')->constrained('warehouses')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warehouse_id');
        });
    }
};
```

- [ ] **Step 2: تشغيل الـ migration والتحقق**
  - Run: `php artisan migrate`
  - Expected: يعرض سطر نجاح "add_warehouse_id_to_purchase_orders".

---

## Task 2: Models + Relationships

**Files:**
- Create: `app/Models/ERP/Supplier.php`
- Create: `app/Models/ERP/PurchaseOrder.php`
- Create: `app/Models/ERP/PurchaseOrderItem.php`

**Interfaces:**
- Consumes: عمود `warehouse_id` (Task 1).
- Produces:
  - `Supplier::purchaseOrders()` hasMany(PurchaseOrder) · `Supplier::scopeSearch`
  - `PurchaseOrder::$fillable` يشمل كل الحقول + `supplier_id` + `warehouse_id` · `items()` hasMany · `supplier()`/`warehouse()`/`creator()` belongsTo · `statusLabel()` · `scopeSearch` · `isPartiallyReceived()`
  - `PurchaseOrderItem::purchaseOrder()` belongsTo · `product()` belongsTo · `remaining()` · `isFullyReceived()`

- [ ] **Step 1: إنشاء `Supplier`**

```php
<?php

namespace App\Models\ERP;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'contact_name',
        'phone',
        'email',
        'tax_number',
        'address',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('contact_name', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%");
        });
    }
}
```

- [ ] **Step 2: إنشاء `PurchaseOrder`**

```php
<?php

namespace App\Models\ERP;

use App\Enums\PurchaseOrderStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_number',
        'supplier_id',
        'status',
        'order_date',
        'expected_date',
        'received_at',
        'total',
        'notes',
        'created_by',
        'warehouse_id',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'expected_date' => 'date',
            'received_at' => 'datetime',
            'total' => 'decimal:2',
        ];
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusLabel(): string
    {
        return PurchaseOrderStatus::from($this->status)?->label() ?? $this->status;
    }

    public function isPartiallyReceived(): bool
    {
        $total = $this->items->sum('quantity');
        $received = $this->items->sum('received_qty');

        return $total > 0 && $received > 0 && $received < $total;
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! $term) {
            return $query;
        }

        return $query->where('order_number', 'like', "%{$term}%")
            ->orWhereHas('supplier', fn ($q) => $q->where('name', 'like', "%{$term}%"));
    }
}
```

- [ ] **Step 3: إنشاء `PurchaseOrderItem`**

```php
<?php

namespace App\Models\ERP;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'quantity',
        'received_qty',
        'unit_cost',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'received_qty' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function remaining(): float
    {
        return (float) $this->quantity - (float) $this->received_qty;
    }

    public function isFullyReceived(): bool
    {
        return $this->remaining() <= 0;
    }
}
```

- [ ] **Step 4: التحقق**
  - لا توجد Tests نهائية بعد؛ تحقق يدوي عبر `php artisan tinker` أن الفئات تُحمَّل (لا أخطاء parse).

---

## Task 3: Enum

**Files:**
- Create: `app/Enums/PurchaseOrderStatus.php`

**Interfaces:**
- Consumes: `PurchaseOrder::statusLabel()` (Task 2).
- Produces: `PurchaseOrderStatus::Draft|Confirmed|Received|Cancelled` · `label()` · `color()` · `list()` — مستهلكة في Controller و Views و Tests.

- [ ] **Step 1: إنشاء الملف** (انسخ بنية `SalesOrderStatus` واستبدل القيم)

```php
<?php

namespace App\Enums;

enum PurchaseOrderStatus: string
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case Received = 'received';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'مسودة',
            self::Confirmed => 'مؤكد',
            self::Received => 'مستلم كاملًا',
            self::Cancelled => 'ملغي',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
            self::Confirmed => 'bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300',
            self::Received => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300',
            self::Cancelled => 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300',
        };
    }

    public static function list(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all();
    }
}
```

- [ ] **Step 2: التحقق**
  - `php -l app/Enums/PurchaseOrderStatus.php` → "No syntax errors".

---

## Task 4: PurchaseOrderService

**Files:**
- Create: `app/Services/ERP/PurchaseOrderService.php`

**Interfaces:**
- Consumes: `PurchaseOrder`/`PurchaseOrderItem`/`PurchaseOrderStatus` (Tasks 2-3) · `StockService::in()` · `AuditLogger` · `Warehouse`.
- Produces: `recalculateTotals(PurchaseOrder $order): PurchaseOrder` · `confirm(PurchaseOrder $order): PurchaseOrder` · `receive(PurchaseOrder $order, array $quantities): PurchaseOrder` · `cancel(PurchaseOrder $order): PurchaseOrder` — كلها تُستهلك من `PurchaseOrderController` (Task 6).

- [ ] **Step 1: إنشاء الملف** (الـ transaction والتحقق كلها في الـ Service)

```php
<?php

namespace App\Services\ERP;

use App\Enums\PurchaseOrderStatus;
use App\Models\ERP\PurchaseOrder;
use App\Models\ERP\PurchaseOrderItem;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PurchaseOrderService
{
    public function __construct(private readonly StockService $stockService)
    {
    }

    /**
     * Recalculates order total from its items.
     */
    public function recalculateTotals(PurchaseOrder $order): PurchaseOrder
    {
        $order->update([
            'total' => $order->items->sum(fn (PurchaseOrderItem $item) => (float) $item->total),
        ]);

        return $order->fresh();
    }

    /**
     * Confirms a purchase order. Does NOT touch stock.
     */
    public function confirm(PurchaseOrder $order): PurchaseOrder
    {
        if ($order->status !== PurchaseOrderStatus::Draft->value) {
            throw new RuntimeException('لا يمكن تأكيد أمر غير مسودة');
        }

        $old = $order->only('status');
        $order->update(['status' => PurchaseOrderStatus::Confirmed->value]);

        AuditLogger::log('confirmed', $order, $old, $order->fresh()->only(['status']));

        return $order->fresh();
    }

    /**
     * Partially or fully receives stock for a confirmed order.
     * Uses the warehouse saved on the order. Every received line
     * creates its own stock movement. Any rule violation rolls back.
     */
    public function receive(PurchaseOrder $order, array $quantities): PurchaseOrder
    {
        return DB::transaction(function () use ($order, $quantities) {
            if ($order->status !== PurchaseOrderStatus::Confirmed->value) {
                throw new RuntimeException('يجب تأكيد أمر الشراء أولًا قبل الاستلام');
            }

            if (! $order->warehouse) {
                throw new RuntimeException('لا يوجد مستودع محدد لأمر الشراء');
            }

            foreach ($order->items as $item) {
                $qty = (float) ($quantities[$item->id] ?? 0);

                if ($qty <= 0) {
                    continue;
                }

                if ($qty > $item->remaining()) {
                    throw new RuntimeException(
                        "الكمية المستلمة من {$item->product?->name} تتجاوز المتبقي ({$item->remaining()})"
                    );
                }

                $item->update(['received_qty' => (float) $item->received_qty + $qty]);

                if ($item->product) {
                    $this->stockService->in(
                        $item->product,
                        $order->warehouse,
                        $qty,
                        'purchase_order',
                        $order,
                        "أمر شراء {$order->order_number}"
                    );
                }
            }

            $order->refresh();

            if ($order->items->every(fn (PurchaseOrderItem $item) => $item->isFullyReceived())) {
                $old = $order->only('status');
                $order->update([
                    'status' => PurchaseOrderStatus::Received->value,
                    'received_at' => now(),
                ]);
                AuditLogger::log('received', $order, $old, ['status' => PurchaseOrderStatus::Received->value]);
            }

            return $order->fresh();
        });
    }

    /**
     * Cancels a draft/confirmed order. Forbidden once anything was received.
     */
    public function cancel(PurchaseOrder $order): PurchaseOrder
    {
        if (in_array($order->status, [
            PurchaseOrderStatus::Received->value,
            PurchaseOrderStatus::Cancelled->value,
        ], true)) {
            throw new RuntimeException('لا يمكن إلغاء أمر مستلم أو ملغي مسبقًا');
        }

        foreach ($order->items as $item) {
            if ((float) $item->received_qty > 0) {
                throw new RuntimeException('لا يمكن إلغاء أمر شراء تم استلام كميات منه');
            }
        }

        $old = $order->only('status');
        $order->update(['status' => PurchaseOrderStatus::Cancelled->value]);

        AuditLogger::log('cancelled', $order, $old, ['status' => PurchaseOrderStatus::Cancelled->value]);

        return $order->fresh();
    }
}
```

- [ ] **Step 2: فحص صياغة**
  - `php -l app/Services/ERP/PurchaseOrderService.php` → "No syntax errors".
  - ملاحظة: لا حاجة لـ `recalculateTotals` في workflows مؤقتًا، لكنها موجودة للاستخدام عند الإنشاء/التعديل.

---

## Task 5: SupplierController

**Files:**
- Create: `app/Http/Controllers/ERP/SupplierController.php`

**Interfaces:**
- Consumes: `Supplier::scopeSearch` (Task 2) · `PurchaseOrder` للتحقق من المورد · `AuditLogger`.
- Produces: `index/create/store/edit/update/destroy` — تُستهلك في Routes (Task 7) و Views (Task 8) و Tests (Task 11).

- [ ] **Step 1: إنشاء الملف**

```php
<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Models\ERP\PurchaseOrder;
use App\Models\ERP\Supplier;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(Request $request): View
    {
        $suppliers = Supplier::query()
            ->withCount('purchaseOrders')
            ->search($request->get('search'))
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('erp.suppliers.index', [
            'suppliers' => $suppliers,
            'activeStatus' => $request->get('is_active'),
        ]);
    }

    public function create(): View
    {
        return view('erp.suppliers.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $supplier = Supplier::create($data + ['is_active' => $request->boolean('is_active', true)]);

        AuditLogger::log('created', $supplier, null, $supplier->toArray());

        return redirect()->route('suppliers.index')->with('success', 'تم إضافة المورد بنجاح');
    }

    public function edit(Supplier $supplier): View
    {
        return view('erp.suppliers.edit', ['supplier' => $supplier]);
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $data = $this->validated($request);

        $old = $supplier->only(array_keys($data));
        $supplier->update($data + ['is_active' => $request->boolean('is_active', true)]);

        AuditLogger::log('updated', $supplier, $old, $supplier->fresh()->only(array_keys($data)));

        return redirect()->route('suppliers.index')->with('success', 'تم تحديث المورد بنجاح');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        $hasActiveOrders = PurchaseOrder::where('supplier_id', $supplier->id)
            ->whereIn('status', ['draft', 'confirmed'])
            ->exists();

        if ($hasActiveOrders) {
            return back()->with('error', 'لا يمكن حذف مورد لديه أوامر شراء نشطة');
        }

        AuditLogger::log('deleted', $supplier, $supplier->toArray(), null);
        $supplier->delete();

        return redirect()->route('suppliers.index')->with('success', 'تم حذف المورد');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
```

- [ ] **Step 2: فحص صياغة**
  - `php -l app/Http/Controllers/ERP/SupplierController.php` → "No syntax errors".

---

## Task 6: PurchaseOrderController

**Files:**
- Create: `app/Http/Controllers/ERP/PurchaseOrderController.php`

**Interfaces:**
- Consumes: `PurchaseOrderService` (Task 4) · `PurchaseOrder`/`Supplier`/`Warehouse`/`Product`/`PurchaseOrderStatus` (Tasks 2-3) · `AuditLogger`.
- Produces: `index/create/store/show/edit/update/destroy/confirm/receive/cancel` — تُستهلك في Routes (Task 7) و Views (Task 9) و Tests (Task 11).

- [ ] **Step 1: إنشاء الملف**

```php
<?php

namespace App\Http\Controllers\ERP;

use App\Enums\PurchaseOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\ERP\Product;
use App\Models\ERP\PurchaseOrder;
use App\Models\ERP\Supplier;
use App\Models\ERP\Warehouse;
use App\Services\AuditLogger;
use App\Services\ERP\PurchaseOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class PurchaseOrderController extends Controller
{
    public function __construct(private readonly PurchaseOrderService $orderService)
    {
    }

    public function index(Request $request): View
    {
        $orders = PurchaseOrder::query()
            ->with(['supplier:id,name', 'creator:id,name', 'warehouse:id,name'])
            ->search($request->get('search'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->get('status')))
            ->when($request->filled('supplier_id'), fn ($q) => $q->where('supplier_id', $request->get('supplier_id')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('erp.purchase-orders.index', [
            'orders' => $orders,
            'statuses' => PurchaseOrderStatus::list(),
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
            'activeStatus' => $request->get('status'),
            'activeSupplier' => $request->get('supplier_id'),
        ]);
    }

    public function create(): View
    {
        return view('erp.purchase-orders.create', [
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'warehouses' => Warehouse::active()->orderBy('name')->get(['id', 'name']),
            'products' => Product::where('is_active', true)->orderBy('name')->get(['id', 'name', 'sku', 'purchase_cost', 'min_stock']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $items = $this->validatedItems($request);

        $order = PurchaseOrder::create([
            'order_number' => $this->nextNumber(),
            'supplier_id' => $data['supplier_id'],
            'warehouse_id' => $data['warehouse_id'],
            'status' => PurchaseOrderStatus::Draft->value,
            'order_date' => $data['order_date'] ?? now()->toDateString(),
            'expected_date' => $data['expected_date'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => auth()->id(),
        ]);

        foreach ($items as $line) {
            $product = Product::find($line['product_id']);
            $total = round((float) $line['quantity'] * (float) $line['unit_cost'], 2);

            $order->items()->create([
                'product_id' => $product?->id,
                'quantity' => $line['quantity'],
                'unit_cost' => $line['unit_cost'],
                'total' => $total,
            ]);
        }

        $this->orderService->recalculateTotals($order);
        AuditLogger::log('created', $order, null, $order->fresh()->toArray());

        return redirect()->route('purchase-orders.show', $order)->with('success', 'تم إنشاء أمر الشراء بنجاح');
    }

    public function show(PurchaseOrder $purchaseOrder): View
    {
        $purchaseOrder->load(['supplier', 'items.product:id,name,sku', 'creator:id,name', 'warehouse:id,name']);

        return view('erp.purchase-orders.show', ['order' => $purchaseOrder]);
    }

    public function edit(PurchaseOrder $purchaseOrder): View
    {
        if ($purchaseOrder->status !== PurchaseOrderStatus::Draft->value) {
            abort(403, 'لا يمكن تعديل أمر تم تأكيده أو استلامه');
        }

        $purchaseOrder->load('items');

        return view('erp.purchase-orders.edit', [
            'order' => $purchaseOrder,
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'warehouses' => Warehouse::active()->orderBy('name')->get(['id', 'name']),
            'products' => Product::where('is_active', true)->orderBy('name')->get(['id', 'name', 'sku', 'purchase_cost', 'min_stock']),
        ]);
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if ($purchaseOrder->status !== PurchaseOrderStatus::Draft->value) {
            return back()->with('error', 'لا يمكن تعديل أمر تم تأكيده أو استلامه');
        }

        $data = $this->validated($request);
        $items = $this->validatedItems($request);

        $purchaseOrder->update([
            'supplier_id' => $data['supplier_id'],
            'warehouse_id' => $data['warehouse_id'],
            'order_date' => $data['order_date'] ?? now()->toDateString(),
            'expected_date' => $data['expected_date'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        $purchaseOrder->items()->delete();
        foreach ($items as $line) {
            $product = Product::find($line['product_id']);
            $purchaseOrder->items()->create([
                'product_id' => $product?->id,
                'quantity' => $line['quantity'],
                'unit_cost' => $line['unit_cost'],
                'total' => round((float) $line['quantity'] * (float) $line['unit_cost'], 2),
            ]);
        }

        $this->orderService->recalculateTotals($purchaseOrder);
        AuditLogger::log('updated', $purchaseOrder, null, $purchaseOrder->fresh()->toArray());

        return redirect()->route('purchase-orders.show', $purchaseOrder)->with('success', 'تم تحديث أمر الشراء');
    }

    public function destroy(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if ($purchaseOrder->status !== PurchaseOrderStatus::Draft->value) {
            return back()->with('error', 'لا يمكن حذف أمر تم تأكيده');
        }

        AuditLogger::log('deleted', $purchaseOrder, $purchaseOrder->toArray(), null);
        $purchaseOrder->delete();

        return redirect()->route('purchase-orders.index')->with('success', 'تم حذف أمر الشراء');
    }

    public function confirm(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        try {
            $this->orderService->confirm($purchaseOrder);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'تم تأكيد أمر الشراء');
    }

    public function receive(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        try {
            $quantities = collect($request->get('quantities', []))->map(fn ($q) => (float) $q)->all();
            $this->orderService->receive($purchaseOrder, $quantities);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'تم استلام الكميات وإضافتها إلى المخزون');
    }

    public function cancel(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        try {
            $this->orderService->cancel($purchaseOrder);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'تم إلغاء أمر الشراء');
    }

    private function nextNumber(): string
    {
        $max = PurchaseOrder::withTrashed()->max('id') ?? 0;

        return 'PO-'.str_pad((string) ($max + 1), 6, '0', STR_PAD_LEFT);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'order_date' => ['nullable', 'date'],
            'expected_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function validatedItems(Request $request): array
    {
        $items = collect($request->get('items', []))->filter(fn ($line) => ! empty($line['product_id']) && (float) ($line['quantity'] ?? 0) > 0);

        return $items->values()->map(function ($line) {
            return [
                'product_id' => $line['product_id'],
                'quantity' => (float) $line['quantity'],
                'unit_cost' => (float) ($line['unit_cost'] ?? 0),
            ];
        })->all();
    }
}
```

- [ ] **Step 2: فحص صياغة**
  - `php -l app/Http/Controllers/ERP/PurchaseOrderController.php` → "No syntax errors".

---

## Task 7: Routes + RBAC

**Files:**
- Modify: `routes/web.php` (استبدال سطري الـ Placeholder لـ suppliers و purchase-orders)
- Modify: `database/seeders/RbacSeeder.php` (إضافة صلاحية `cancel_purchase_orders`)

**Interfaces:**
- Consumes: Controllers (Tasks 5-6).
- Produces: routes `suppliers.*` و `purchase-orders.*` · صلاحية `cancel_purchase_orders` مربوطة بالـ purchasing-officer.

- [ ] **Step 1: تعديل `routes/web.php`**
  - أضف import: `use App\Http\Controllers\ERP\PurchaseOrderController;` و `use App\Http\Controllers\ERP\SupplierController;`
  - استبدل السطرين 100-101 (Placeholder لـ suppliers و purchase-orders) بهذا:

```php
    Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index')->middleware('permission:view_suppliers');
    Route::get('/suppliers/create', [SupplierController::class, 'create'])->name('suppliers.create')->middleware('permission:create_suppliers');
    Route::post('/suppliers', [SupplierController::class, 'store'])->name('suppliers.store')->middleware('permission:create_suppliers');
    Route::get('/suppliers/{supplier}/edit', [SupplierController::class, 'edit'])->name('suppliers.edit')->middleware('permission:edit_suppliers');
    Route::match(['put', 'patch'], '/suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update')->middleware('permission:edit_suppliers');
    Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy')->middleware('permission:delete_suppliers');

    Route::get('/purchase-orders', [PurchaseOrderController::class, 'index'])->name('purchase-orders.index')->middleware('permission:view_purchase_orders');
    Route::get('/purchase-orders/create', [PurchaseOrderController::class, 'create'])->name('purchase-orders.create')->middleware('permission:create_purchase_orders');
    Route::post('/purchase-orders', [PurchaseOrderController::class, 'store'])->name('purchase-orders.store')->middleware('permission:create_purchase_orders');
    Route::get('/purchase-orders/{purchase_order}', [PurchaseOrderController::class, 'show'])->name('purchase-orders.show')->middleware('permission:view_purchase_orders');
    Route::get('/purchase-orders/{purchase_order}/edit', [PurchaseOrderController::class, 'edit'])->name('purchase-orders.edit')->middleware('permission:create_purchase_orders');
    Route::match(['put', 'patch'], '/purchase-orders/{purchase_order}', [PurchaseOrderController::class, 'update'])->name('purchase-orders.update')->middleware('permission:create_purchase_orders');
    Route::delete('/purchase-orders/{purchase_order}', [PurchaseOrderController::class, 'destroy'])->name('purchase-orders.destroy')->middleware('permission:cancel_purchase_orders');
    Route::post('/purchase-orders/{purchase_order}/confirm', [PurchaseOrderController::class, 'confirm'])->name('purchase-orders.confirm')->middleware('permission:confirm_purchase_orders');
    Route::post('/purchase-orders/{purchase_order}/receive', [PurchaseOrderController::class, 'receive'])->name('purchase-orders.receive')->middleware('permission:receive_purchase_orders');
    Route::post('/purchase-orders/{purchase_order}/cancel', [PurchaseOrderController::class, 'cancel'])->name('purchase-orders.cancel')->middleware('permission:cancel_purchase_orders');
```

- [ ] **Step 2: تعديل `RbacSeeder.php`**
  - أضف صلاحية جديدة داخل `permissions()` بعد `receive_purchase_orders` (سطر 240):

```php
            ['name' => 'إلغاء أمر شراء', 'slug' => 'cancel_purchase_orders', 'group' => 'purchase_orders'],
```

  - في `roles()` تحت `purchasing-officer` (سطر 161) أضف cancel في قائمة purchase_orders:

```php
                    'purchase_orders' => ['view_purchase_orders', 'create_purchase_orders', 'confirm_purchase_orders', 'receive_purchase_orders', 'cancel_purchase_orders'],
```

- [ ] **Step 3: تشغيل الـ seeder**
  - Run: `php artisan db:seed --class=RbacSeeder`
  - Expected: صلاحية `cancel_purchase_orders` مضافة وقائمة المستخدمين التجريبيين موجودة.

- [ ] **Step 4: التحقق من الـ routes**
  - Run: `php artisan route:list --name=purchase-orders`
  - Expected: 9 routes باسم ‏purchase-orders.* تظهر (index/create/store/show/edit/update/destroy/confirm/receive/cancel).

---

## Task 8: Supplier Views

**Files:**
- Create: `resources/views/erp/suppliers/index.blade.php`
- Create: `resources/views/erp/suppliers/create.blade.php`
- Create: `resources/views/erp/suppliers/edit.blade.php`
- Create: `resources/views/erp/suppliers/_form_fields.blade.php`

**Interfaces:**
- Consumes: `SupplierController` (Task 5) · `$suppliers` / `$supplier`.
- Produces: صفحات عربية RTL مع أزرار حسب RBAC.

- [ ] **Step 1: `_form_fields.blade.php`** (نموذج مشترك)

```blade
@php $supplier ??= null; @endphp

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div>
        <x-input-label for="name" value="اسم المورد" />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" value="{{ old('name', $supplier?->name) }}" required />
        <x-input-error :messages="$errors->get('name')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="contact_name" value="اسم جهة الاتصال (اختياري)" />
        <x-text-input id="contact_name" name="contact_name" type="text" class="mt-1 block w-full" value="{{ old('contact_name', $supplier?->contact_name) }}" />
    </div>
    <div>
        <x-input-label for="phone" value="الهاتف" />
        <x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full" value="{{ old('phone', $supplier?->phone) }}" />
    </div>
    <div>
        <x-input-label for="email" value="البريد الإلكتروني" />
        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" value="{{ old('email', $supplier?->email) }}" />
    </div>
    <div>
        <x-input-label for="tax_number" value="الرقم الضريبي" />
        <x-text-input id="tax_number" name="tax_number" type="text" class="mt-1 block w-full" value="{{ old('tax_number', $supplier?->tax_number) }}" />
    </div>
    <div class="sm:col-span-2">
        <x-input-label for="address" value="العنوان" />
        <x-text-input id="address" name="address" type="text" class="mt-1 block w-full" value="{{ old('address', $supplier?->address) }}" />
    </div>
</div>

<div class="mt-4">
    <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $supplier?->is_active ?? true)) class="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800" />
        مورد نشط
    </label>
</div>
```

- [ ] **Step 2: `create.blade.php`**

```blade
<x-app-layout>
    <x-slot name="title">{{ __('إضافة مورد') }}</x-slot>

    <div class="mx-auto max-w-3xl">
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('إضافة مورد') }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">أدخل بيانات المورد الجديد</p>
        </div>

        <form method="POST" action="{{ route('suppliers.store') }}" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            @csrf
            @include('erp.suppliers._form_fields')

            <div class="mt-6 flex items-center gap-3">
                <x-primary-button>{{ __('حفظ') }}</x-primary-button>
                <a href="{{ route('suppliers.index') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">{{ __('إلغاء') }}</a>
            </div>
        </form>
    </div>
</x-app-layout>
```

- [ ] **Step 3: `edit.blade.php`** (مطابق لـ create مع `method('PUT')` و `$supplier`)

```blade
<x-app-layout>
    <x-slot name="title">{{ __('تعديل مورد') }}</x-slot>

    <div class="mx-auto max-w-3xl">
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('تعديل مورد') }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $supplier->name }}</p>
        </div>

        <form method="POST" action="{{ route('suppliers.update', $supplier) }}" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            @csrf
            @method('PUT')
            @include('erp.suppliers._form_fields')

            <div class="mt-6 flex items-center gap-3">
                <x-primary-button>{{ __('حفظ التغييرات') }}</x-primary-button>
                <a href="{{ route('suppliers.index') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">{{ __('إلغاء') }}</a>
            </div>
        </form>
    </div>
</x-app-layout>
```

- [ ] **Step 4: `index.blade.php`** (بحث + جدول + delete confirmation)

```blade
<x-app-layout>
    <x-slot name="title">{{ __('الموردون') }}</x-slot>

    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('الموردون') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">إدارة الموردين وجهات الاتصال</p>
            </div>
            @can('permission.create_suppliers')
                <a href="{{ route('suppliers.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                    {{ __('إضافة مورد') }}
                </a>
            @endcan
        </div>

        <form method="GET" action="{{ route('suppliers.index') }}" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="sm:col-span-2">
                    <x-input-label for="search" value="بحث" />
                    <x-text-input id="search" name="search" type="text" class="mt-1 block w-full" value="{{ request('search') }}" placeholder="الاسم أو جهة الاتصال أو الهاتف..." />
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600">تصفية</button>
                    @if (request()->has('search'))
                        <a href="{{ route('suppliers.index') }}" class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-800">مسح</a>
                    @endif
                </div>
            </div>
        </form>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-800/60">
                        <tr>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">الاسم</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">جهة الاتصال</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">الهاتف</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">أوامر الشراء</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">الحالة</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($suppliers as $supplier)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="px-4 py-3 font-medium text-slate-900 dark:text-white">{{ $supplier->name }}</td>
                                <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">{{ $supplier->contact_name ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300" dir="ltr">{{ $supplier->phone ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">{{ $supplier->purchase_orders_count }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $supplier->is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400' }}">
                                        {{ $supplier->is_active ? 'نشط' : 'موقوف' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-1">
                                        @can('permission.edit_suppliers')
                                            <a href="{{ route('suppliers.edit', $supplier) }}" class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-300" title="تعديل">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                            </a>
                                        @endcan
                                        @can('permission.delete_suppliers')
                                            <form method="POST" action="{{ route('suppliers.destroy', $supplier) }}" onsubmit="return confirm('حذف المورد؟')">
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
                                <td colspan="6" class="px-4 py-16 text-center text-sm text-slate-500 dark:text-slate-400">لا يوجد موردون بعد</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($suppliers->hasPages())
                <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-700">{{ $suppliers->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 5: التحقق**
  - `php -l` غير مناسب للبليد؛ تحقق بعد Task 11 (تغطي الاختبارات عرض الصفحات).

---

## Task 9: Purchase Order Views

**Files:**
- Create: `resources/views/erp/purchase-orders/index.blade.php`
- Create: `resources/views/erp/purchase-orders/create.blade.php`
- Create: `resources/views/erp/purchase-orders/edit.blade.php`
- Create: `resources/views/erp/purchase-orders/show.blade.php`

**Interfaces:**
- Consumes: `PurchaseOrderController` (Task 6) · `PurchaseOrderStatus::color/label` · `$orders`/`$order`/`$suppliers`/`$warehouses`/`$products`.
- Produces: صفحات عربية RTL بأزرار مشروطة بالحالة والصلاحية.

- [ ] **Step 1: `create.blade.php`** (بنود ديناميكية — انسخ بنية sales-orders/create مع اختلاف الحقول)

```blade
<x-app-layout>
    <x-slot name="title">{{ __('إنشاء أمر شراء') }}</x-slot>

    <div class="mx-auto max-w-5xl space-y-6">
        <div>
            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('إنشاء أمر شراء') }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">اختر المورد والمستودع وأصناف الأمر</p>
        </div>

        <form method="POST" action="{{ route('purchase-orders.store') }}" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900" x-data="purchaseOrderForm()">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <x-input-label for="supplier_id" value="المورد" />
                    <select id="supplier_id" name="supplier_id" required class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                        <option value="">اختر المورد</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected(old('supplier_id') == $supplier->id)>{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('supplier_id')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="warehouse_id" value="المستودع" />
                    <select id="warehouse_id" name="warehouse_id" required class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                        <option value="">اختر المستودع</option>
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" @selected(old('warehouse_id') == $warehouse->id)>{{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('warehouse_id')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="order_date" value="تاريخ الأمر" />
                    <x-text-input id="order_date" name="order_date" type="date" class="mt-1 block w-full" value="{{ old('order_date', now()->toDateString()) }}" />
                    <x-input-error :messages="$errors->get('order_date')" class="mt-2" />
                </div>
            </div>

            <div class="mt-4">
                <x-input-label for="expected_date" value="تاريخ الاستلام المتوقع (اختياري)" />
                <x-text-input id="expected_date" name="expected_date" type="date" class="mt-1 block w-full" value="{{ old('expected_date') }}" />
            </div>

            {{-- Items --}}
            <div class="mt-8">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">الأصناف</h3>
                    <button type="button" @click="addRow()" class="inline-flex items-center gap-1 rounded-lg bg-indigo-50 px-3 py-1.5 text-xs font-medium text-indigo-600 hover:bg-indigo-100 dark:bg-indigo-500/10 dark:text-indigo-400">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                        إضافة صنف
                    </button>
                </div>

                <div class="mt-3 space-y-2">
                    <template x-for="(row, index) in rows" :key="index">
                        <div class="grid grid-cols-12 gap-2 items-center rounded-lg border border-slate-200 p-2 dark:border-slate-700">
                            <div class="col-span-5">
                                <select :name="'items['+index+'][product_id]'" x-model.number="row.product_id" required class="block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                                    <option value="">اختر المنتج</option>
                                    @foreach ($products as $product)
                                        <option value="{{ $product->id }}" data-price="{{ $product->purchase_cost }}">{{ $product->name }} · {{ $product->sku }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-span-2">
                                <x-text-input type="number" step="0.01" min="0.01" x-model.number="row.quantity" x-bind:name="'items['+index+'][quantity]'" class="block w-full text-sm" placeholder="الكمية" required />
                            </div>
                            <div class="col-span-2">
                                <x-text-input type="number" step="0.01" min="0" x-model.number="row.unit_cost" x-bind:name="'items['+index+'][unit_cost]'" class="block w-full text-sm" placeholder="تكلفة الوحدة" required />
                            </div>
                            <div class="col-span-2 text-sm font-medium text-slate-700 dark:text-slate-200">
                                <span x-text="(row.quantity * row.unit_cost).toFixed(2)"></span> ₪
                            </div>
                            <div class="col-span-1 flex justify-end">
                                <button type="button" @click="removeRow(index)" x-show="rows.length > 1" class="rounded-md p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
                <x-input-error :messages="$errors->get('items')" class="mt-2" />
            </div>

            <div class="mt-4">
                <x-input-label for="notes" value="ملاحظات (اختياري)" />
                <textarea id="notes" name="notes" rows="2" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">{{ old('notes') }}</textarea>
            </div>

            <div class="mt-6 flex items-center gap-3">
                <x-primary-button>{{ __('حفظ') }}</x-primary-button>
                <a href="{{ route('purchase-orders.index') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">{{ __('إلغاء') }}</a>
            </div>
        </form>
    </div>

    <script>
        function purchaseOrderForm() {
            return {
                rows: [{ product_id: '', quantity: 1, unit_cost: '' }],
                addRow() {
                    this.rows.push({ product_id: '', quantity: 1, unit_cost: '' });
                },
                removeRow(index) {
                    if (this.rows.length > 1) this.rows.splice(index, 1);
                }
            }
        }
    </script>
</x-app-layout>
```

- [ ] **Step 2: `show.blade.php`** — التفاصيل + جدول بنود + أشكال حسب الحالة (استلام جزئي في confirmed)

```blade
<x-app-layout>
    <x-slot name="title">{{ __('أمر شراء') }} {{ $order->order_number }}</x-slot>

    @php
        $status = \App\Enums\PurchaseOrderStatus::from($order->status);
    @endphp

    <div class="mx-auto max-w-5xl space-y-6">
        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <h2 class="text-2xl font-bold text-slate-900 dark:text-white"><span class="font-mono" dir="ltr">{{ $order->order_number }}</span></h2>
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $status?->color() }}">{{ $status?->label() }}</span>
                </div>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">أُنشئ في {{ $order->order_date?->format('Y-m-d') }} بواسطة {{ $order->creator?->name ?? '—' }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if ($order->status === 'draft')
                    @can('permission.confirm_purchase_orders')
                        <form method="POST" action="{{ route('purchase-orders.confirm', $order) }}" onsubmit="return confirm('تأكيد أمر الشراء؟')">
                            @csrf
                            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                تأكيد الأمر
                            </button>
                        </form>
                    @endcan
                    @can('permission.create_purchase_orders')
                        <a href="{{ route('purchase-orders.edit', $order) }}" class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-800">تعديل</a>
                    @endcan
                    @can('permission.cancel_purchase_orders')
                        <form method="POST" action="{{ route('purchase-orders.cancel', $order) }}" onsubmit="return confirm('إلغاء أمر الشراء؟')">
                            @csrf
                            <button type="submit" class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-800">إلغاء</button>
                        </form>
                        <form method="POST" action="{{ route('purchase-orders.destroy', $order) }}" onsubmit="return confirm('حذف أمر الشراء؟ (مسودة فقط)')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="inline-flex items-center rounded-lg border border-rose-200 px-4 py-2 text-sm font-medium text-rose-600 hover:bg-rose-50 dark:border-rose-500/30 dark:hover:bg-rose-500/10">حذف</button>
                        </form>
                    @endcan
                @elseif ($order->status === 'confirmed')
                    @can('permission.cancel_purchase_orders')
                        <form method="POST" action="{{ route('purchase-orders.cancel', $order) }}" onsubmit="return confirm('إلغاء أمر الشراء؟')">
                            @csrf
                            <button type="submit" class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-800">إلغاء</button>
                        </form>
                    @endcan
                @endif
            </div>
        </div>

        @if ($order->status === 'cancelled')
            <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300">تم إلغاء أمر الشراء.</div>
        @endif

        {{-- Details grid --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="space-y-6">
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">المورد</h3>
                    <p class="mt-2 text-lg font-bold text-slate-900 dark:text-white">{{ $order->supplier?->name }}</p>
                    @if ($order->supplier?->phone)
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400" dir="ltr">{{ $order->supplier->phone }}</p>
                    @endif
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">المستودع المستلم</h3>
                    <p class="mt-2 text-sm font-medium text-slate-700 dark:text-slate-200">{{ $order->warehouse?->name ?? '—' }}</p>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">الملخص</h3>
                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex items-center justify-between"><dt class="text-slate-500 dark:text-slate-400">عدد الأصناف</dt><dd class="font-medium text-slate-900 dark:text-white">{{ $order->items->count() }}</dd></div>
                        @if ($order->expected_date)
                            <div class="flex items-center justify-between"><dt class="text-slate-500 dark:text-slate-400">متوقع الاستلام</dt><dd class="font-medium text-slate-900 dark:text-white">{{ $order->expected_date->format('Y-m-d') }}</dd></div>
                        @endif
                        @if ($order->received_at)
                            <div class="flex items-center justify-between"><dt class="text-slate-500 dark:text-slate-400">آخر استلام</dt><dd class="font-medium text-slate-900 dark:text-white">{{ $order->received_at->format('Y-m-d H:i') }}</dd></div>
                        @endif
                        <div class="border-t border-slate-200 pt-2 dark:border-slate-700">
                            <div class="flex items-center justify-between"><dt class="text-base font-semibold text-slate-900 dark:text-white">الإجمالي</dt><dd class="text-base font-bold text-indigo-600 dark:text-indigo-400">{{ number_format((float) $order->total, 2) }} ₪</dd></div>
                        </div>
                    </dl>
                </div>
            </div>

            {{-- Items table --}}
            <div class="lg:col-span-2 rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-700">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">الأصناف</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                        <thead class="bg-slate-50 dark:bg-slate-800/60">
                            <tr>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">المنتج</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">المطلوب</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">المستلم</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">المتبقي</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">تكلفة الوحدة</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">الإجمالي</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($order->items as $item)
                                <tr>
                                    <td class="px-4 py-3 font-medium text-slate-900 dark:text-white">{{ $item->product?->name }}</td>
                                    <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">{{ (float) $item->quantity }}</td>
                                    <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">{{ (float) $item->received_qty }}</td>
                                    <td class="px-4 py-3 text-sm {{ $item->remaining() > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400' }}">{{ $item->remaining() }}</td>
                                    <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">{{ number_format((float) $item->unit_cost, 2) }} ₪</td>
                                    <td class="px-4 py-3 text-sm font-semibold text-slate-700 dark:text-slate-200">{{ number_format((float) $item->total, 2) }} ₪</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Receive form (confirmed only) --}}
        @if ($order->status === 'confirmed' && $order->items->contains(fn ($item) => $item->remaining() > 0))
            @can('permission.receive_purchase_orders')
                <form method="POST" action="{{ route('purchase-orders.receive', $order) }}" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    @csrf
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">استلام الكميات</h3>
                        <span class="text-xs text-slate-500 dark:text-slate-400">المستودع: {{ $order->warehouse?->name }}</span>
                    </div>

                    <div class="mt-4 space-y-2">
                        @foreach ($order->items as $item)
                            @if ($item->remaining() <= 0)
                                @continue
                            @endif
                            <div class="grid grid-cols-12 gap-2 items-center rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                                <div class="col-span-5">
                                    <div class="font-medium text-slate-900 dark:text-white">{{ $item->product?->name }}</div>
                                    <div class="text-xs text-slate-400">المطلوب {{ (float) $item->quantity }} · المستلم {{ (float) $item->received_qty }} · المتبقي {{ $item->remaining() }}</div>
                                </div>
                                <div class="col-span-4">
                                    <x-text-input type="number" step="0.01" min="1" :max="$item->remaining()" :name="'quantities['.$item->id.']'" class="block w-full text-sm" placeholder="الكمية للاستلام" value="{{ $item->remaining() }}" required />
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-5">
                        <x-primary-button>{{ __('استلام والإضافة للمخزون') }}</x-primary-button>
                    </div>
                </form>
            @endcan
        @endif

        @if ($order->notes)
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">ملاحظات</h3>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ $order->notes }}</p>
            </div>
        @endif
    </div>
</x-app-layout>
```

- [ ] **Step 3: `index.blade.php`** (انسخ بنية sales-orders/index مع تعديل الأعمدة والفلاتر)

```blade
<x-app-layout>
    <x-slot name="title">{{ __('أوامر الشراء') }}</x-slot>

    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('أوامر الشراء') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">إنشاء أوامر الشراء وتأكيدها واستلام المخزون</p>
            </div>
            @can('permission.create_purchase_orders')
                <a href="{{ route('purchase-orders.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                    {{ __('إنشاء أمر شراء') }}
                </a>
            @endcan
        </div>

        <form method="GET" action="{{ route('purchase-orders.index') }}" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <x-input-label for="search" value="بحث" />
                    <x-text-input id="search" name="search" type="text" class="mt-1 block w-full" value="{{ request('search') }}" placeholder="رقم الأمر أو اسم المورد..." />
                </div>
                <div>
                    <x-input-label for="status" value="الحالة" />
                    <select id="status" name="status" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                        <option value="">كل الحالات</option>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected($activeStatus === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="supplier_id" value="المورد" />
                    <select id="supplier_id" name="supplier_id" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                        <option value="">كل الموردين</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected((string) $activeSupplier === (string) $supplier->id)>{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="mt-3 flex items-center gap-2">
                <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600">تصفية</button>
                @if (request()->hasAny(['search', 'status', 'supplier_id']))
                    <a href="{{ route('purchase-orders.index') }}" class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-800">مسح</a>
                @endif
            </div>
        </form>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-800/60">
                        <tr>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">رقم الأمر</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">المورد</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">التاريخ</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">الإجمالي</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">الحالة</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($orders as $order)
                            @php $orderStatus = \App\Enums\PurchaseOrderStatus::from($order->status); @endphp
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="px-4 py-3">
                                    <a href="{{ route('purchase-orders.show', $order) }}" class="font-medium text-indigo-600 hover:text-indigo-700 dark:text-indigo-400" dir="ltr">{{ $order->order_number }}</a>
                                </td>
                                <td class="px-4 py-3 text-sm font-medium text-slate-900 dark:text-white">{{ $order->supplier?->name }}</td>
                                <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">{{ $order->order_date?->format('Y-m-d') }}</td>
                                <td class="px-4 py-3 text-sm font-semibold text-slate-700 dark:text-slate-200">{{ number_format((float) $order->total, 2) }} ₪</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $orderStatus?->color() }}">{{ $orderStatus?->label() }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-1">
                                        <a href="{{ route('purchase-orders.show', $order) }}" class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-300" title="عرض">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0zm-9 0a9 9 0 0118 0 9 9 0 01-18 0z" /></svg>
                                        </a>
                                        @can('permission.create_purchase_orders')
                                            @if ($order->status === 'draft')
                                                <a href="{{ route('purchase-orders.edit', $order) }}" class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-300" title="تعديل">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                                </a>
                                            @endif
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-16 text-center text-sm text-slate-500 dark:text-slate-400">لا توجد أوامر شراء بعد</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($orders->hasPages())
                <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-700">{{ $orders->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 4: `edit.blade.php`** — انسخ create مع `route('purchase-orders.update', $order)` + `@method('PUT')` + prefill من `$order` (fields: supplier_id/warehouse_id/order_date/expected_date/notes + items prefilled). الحقول تُعبَّأ بـ `old('...', $order->...` وبالبنود من `$order->items`.

---

## Task 10: Validation

**Files:**
- Modify: (لا ملف جديد — الـ validation موزعة بين `PurchaseOrderController::validated()/validatedItems()` و `SupplierController::validated()` و `PurchaseOrderService` من Task 4)

**Interfaces:**
- Consumes: Tasks 4-6.
- Notes: القواعد here هي **التجميع النهائي**؛ تأكد أن الكود الموجود في Task 4/5/6 يطابقها تمامًا.

- [ ] **Step 1: مراجعة قواعد الـ backend مقابل جدول القواعد**
  - تحقق يدويًا من هذه النقاط في الكود الكائن:
    - `receive()`: يرفض غير-confirmed (RuntimeException) + `qty > remaining` + `qty <= 0` تُتجاهل بتصف الايجابيات وبالتحديد `greater than remaining` يرمي.
  - **تأكد من مطابقة:** في `PurchaseOrderService::receive` نقوم بإضافة الكل أو `throw` عند أول مخالفة → الـ transaction يتراجع؛ `qty <= 0` تُتخطى (continue) لا ترمي (لأن النموذج يرسل inputs فارغة لبنود غير مستلمة).
  - قواعد create/update من الـ controller: `supplier_id required exists` · `warehouse_id required exists` · `product_id exists` · `quantity > 0` · `unit_cost >= 0`.
  - `destroy Supplier`: منع فقط لو PO بـ draft/confirmed.
  - `destroy PO`: draft فقط.
  - `cancel PO`: من received/cancelled، ومن أي استلام سابق.

- [ ] **Step 2: اختبار نافذ (اختياري مسبق)**
  - بعد كتابة الاختبارات (Task 11) ستتحقق هذه القواعد بالكامل.

---

## Task 11: Tests

**Files:**
- Create: `tests/Feature/SupplierTest.php`
- Create: `tests/Feature/PurchaseTest.php`

**Interfaces:**
- Consumes: كل ما سبق · `RbacSeeder` (يحوي الآن `cancel_purchase_orders`).

- [ ] **Step 1: `SupplierTest.php`**

```php
<?php

namespace Tests\Feature;

use App\Models\ERP\PurchaseOrder;
use App\Models\ERP\Supplier;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierTest extends TestCase
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

    private function purchasing(): User
    {
        return User::where('email', 'purchasing@crm.test')->firstOrFail();
    }

    private function warehouseManager(): User
    {
        return User::where('email', 'warehouse@crm.test')->firstOrFail();
    }

    private function support(): User
    {
        return User::where('email', 'support@crm.test')->firstOrFail();
    }

    private function makeSupplier(array $overrides = []): Supplier
    {
        return Supplier::create(array_merge([
            'name' => 'مورد تجريبي',
            'phone' => '0599'.random_int(100000, 999999),
            'is_active' => true,
        ], $overrides));
    }

    public function test_admin_can_create_supplier(): void
    {
        $this->actingAs($this->admin())
            ->post(route('suppliers.store'), [
                'name' => 'شركة النور للمستلزمات',
                'contact_name' => 'أحمد',
                'phone' => '0599000001',
                'email' => 'ahmed@noor.test',
                'tax_number' => 'TAX-123',
                'is_active' => '1',
            ])
            ->assertRedirect(route('suppliers.index'));

        $this->assertDatabaseHas('suppliers', ['name' => 'شركة النور للمستلزمات']);
    }

    public function test_supplier_requires_name(): void
    {
        $this->actingAs($this->admin())
            ->post(route('suppliers.store'), ['name' => ''])
            ->assertSessionHasErrors(['name']);
    }

    public function test_purchasing_officer_has_full_access(): void
    {
        $supplier = $this->makeSupplier();

        $this->actingAs($this->purchasing())
            ->get(route('suppliers.index'))->assertOk();

        $this->actingAs($this->purchasing())
            ->post(route('suppliers.store'), ['name' => 'مورد جديد'])
            ->assertRedirect(route('suppliers.index'));

        $this->actingAs($this->purchasing())
            ->put(route('suppliers.update', $supplier), ['name' => 'مورد محدث'])
            ->assertRedirect(route('suppliers.index'));

        $this->actingAs($this->purchasing())
            ->delete(route('suppliers.destroy', $supplier))
            ->assertRedirect(route('suppliers.index'));
    }

    public function test_warehouse_manager_can_only_view(): void
    {
        $this->actingAs($this->warehouseManager())
            ->get(route('suppliers.index'))->assertOk();

        $this->actingAs($this->warehouseManager())
            ->post(route('suppliers.store'), ['name' => 'مورد']) // حفظ/حذف يجب أن يفشل
            ->assertForbidden();
    }

    public function test_support_cannot_access_suppliers(): void
    {
        $this->actingAs($this->support())
            ->get(route('suppliers.index'))
            ->assertForbidden();
    }

    public function test_supplier_cannot_be_deleted_with_active_purchase_order(): void
    {
        $supplier = $this->makeSupplier();

        PurchaseOrder::create([
            'order_number' => 'PO-000001',
            'supplier_id' => $supplier->id,
            'status' => 'confirmed',
            'order_date' => now()->toDateString(),
            'created_by' => $this->admin()->id,
        ]);

        $this->actingAs($this->admin())
            ->delete(route('suppliers.destroy', $supplier))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id]);
    }

    public function test_supplier_can_be_deleted_with_only_historic_orders(): void
    {
        $supplier = $this->makeSupplier();

        PurchaseOrder::create([
            'order_number' => 'PO-000001',
            'supplier_id' => $supplier->id,
            'status' => 'received',
            'order_date' => now()->toDateString(),
            'created_by' => $this->admin()->id,
        ]);

        $this->actingAs($this->admin())
            ->delete(route('suppliers.destroy', $supplier))
            ->assertRedirect(route('suppliers.index'));

        $this->assertDatabaseMissing('suppliers', ['id' => $supplier->id]);
    }
}
```

- [ ] **Step 2: `PurchaseTest.php`**

```php
<?php

namespace Tests\Feature;

use App\Enums\PurchaseOrderStatus;
use App\Models\CRM\Customer;
use App\Models\ERP\Product;
use App\Models\ERP\PurchaseOrder;
use App\Models\ERP\StockMovement;
use App\Models\ERP\Supplier;
use App\Models\ERP\Warehouse;
use App\Models\User;
use App\Services\ERP\StockService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PurchaseTest extends TestCase
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

    private function purchasing(): User
    {
        return User::where('email', 'purchasing@crm.test')->firstOrFail();
    }

    private function warehouseManager(): User
    {
        return User::where('email', 'warehouse@crm.test')->firstOrFail();
    }

    private function support(): User
    {
        return User::where('email', 'support@crm.test')->firstOrFail();
    }

    private function makeSupplier(): Supplier
    {
        return Supplier::create(['name' => 'مورد مشتريات', 'phone' => '0599'.random_int(100000, 999999), 'is_active' => true]);
    }

    private function makeWarehouse(): Warehouse
    {
        return Warehouse::create(['name' => 'مخزن استلام', 'code' => 'WH-'.uniqid(), 'is_active' => true]);
    }

    private function makeProduct(): Product
    {
        return Product::create([
            'name' => 'منتج مشتريات',
            'sku' => 'PUR-'.uniqid(),
            'sale_price' => 200,
            'purchase_cost' => 150,
            'min_stock' => 2,
            'is_active' => true,
        ]);
    }

    private function makeOrder(string $status = 'draft', bool $withItems = true): PurchaseOrder
    {
        $supplier = $this->makeSupplier();
        $warehouse = $this->makeWarehouse();

        $order = PurchaseOrder::create([
            'order_number' => 'PO-'.str_pad((string) (PurchaseOrder::max('id') + 1), 6, '0', STR_PAD_LEFT),
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'status' => $status,
            'order_date' => now()->toDateString(),
            'created_by' => $this->admin()->id,
        ]);

        if ($withItems) {
            $order->items()->create([
                'product_id' => $this->makeProduct()->id,
                'quantity' => 10,
                'unit_cost' => 150,
                'total' => 1500,
            ]);
        }

        return $order;
    }

    public function test_purchasing_officer_can_create_po_with_items(): void
    {
        $supplier = $this->makeSupplier();
        $warehouse = $this->makeWarehouse();
        $product = $this->makeProduct();

        $this->actingAs($this->purchasing())
            ->post(route('purchase-orders.store'), [
                'supplier_id' => $supplier->id,
                'warehouse_id' => $warehouse->id,
                'order_date' => now()->toDateString(),
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 5, 'unit_cost' => 150],
                ],
            ])
            ->assertRedirect();

        $order = PurchaseOrder::first();
        $this->assertSame(PurchaseOrderStatus::Draft->value, $order->status);
        $this->assertSame('PO-000001', $order->order_number);
        $this->assertSame('750.00', $order->total);
    }

    public function test_po_requires_supplier_and_warehouse(): void
    {
        $product = $this->makeProduct();

        $this->actingAs($this->purchasing())
            ->post(route('purchase-orders.store'), [
                'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_cost' => 10]],
            ])
            ->assertSessionHasErrors(['supplier_id', 'warehouse_id']);
    }

    public function test_confirm_changes_status_and_does_not_touch_stock(): void
    {
        $order = $this->makeOrder();

        $this->actingAs($this->purchasing())
            ->post(route('purchase-orders.confirm', $order))
            ->assertSessionHas('success');

        $order->refresh();
        $this->assertSame(PurchaseOrderStatus::Confirmed->value, $order->status);
        $this->assertSame(0, StockMovement::count());
    }

    public function test_partial_receive_adds_stock_and_stays_confirmed(): void
    {
        $order = $this->makeOrder('confirmed');
        $item = $order->items->first();
        $product = $item->product;

        $this->actingAs($this->warehouseManager())
            ->post(route('purchase-orders.receive', $order), [
                'quantities' => [$item->id => 4],
            ])
            ->assertSessionHas('success');

        $order->refresh();
        $item->refresh();

        $this->assertSame('4.00', (string) $item->received_qty);
        $this->assertSame(PurchaseOrderStatus::Confirmed->value, $order->status);

        $this->assertEquals(4.0, (float) $product->stockItems()->where('warehouse_id', $order->warehouse_id)->value('quantity'));
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'purchase_order',
            'direction' => 'in',
            'quantity' => 4,
        ]);
    }

    public function test_full_receive_marks_order_received(): void
    {
        $order = $this->makeOrder('confirmed');
        $item = $order->items->first();

        $this->actingAs($this->warehouseManager())
            ->post(route('purchase-orders.receive', $order), [
                'quantities' => [$item->id => 10],
            ])
            ->assertSessionHas('success');

        $order->refresh();
        $this->assertSame(PurchaseOrderStatus::Received->value, $order->status);
        $this->assertNotNull($order->received_at);
    }

    public function test_receive_rejects_quantity_over_remaining(): void
    {
        $order = $this->makeOrder('confirmed');
        $item = $order->items->first();

        $this->actingAs($this->warehouseManager())
            ->post(route('purchase-orders.receive', $order), [
                'quantities' => [$item->id => 11],
            ])
            ->assertSessionHas('error');

        $item->refresh();
        $this->assertSame('0.00', (string) $item->received_qty);
        $this->assertSame(0, StockMovement::count());
    }

    public function test_receive_rejects_zero_or_negative(): void
    {
        $order = $this->makeOrder('confirmed');
        $item = $order->items->first();

        $this->actingAs($this->warehouseManager())
            ->post(route('purchase-orders.receive', $order), [
                'quantities' => [$item->id => 0],
            ])
            ->assertSessionHas('success'); // 0 = skip, أي لا حركة (مطابقة التسامح للنموذج)

        $this->assertSame(0, StockMovement::count());
        $item->refresh();
        $this->assertSame('0.00', (string) $item->received_qty);
    }

    public function test_receive_rejected_unless_confirmed(): void
    {
        $draft = $this->makeOrder('draft');
        $item = $draft->items->first();

        $this->actingAs($this->warehouseManager())
            ->post(route('purchase-orders.receive', $draft), [
                'quantities' => [$item->id => 5],
            ])
            ->assertSessionHas('error');

        $received = $this->makeOrder('received');
        $ritem = $received->items->first();

        $this->actingAs($this->warehouseManager())
            ->post(route('purchase-orders.receive', $received), [
                'quantities' => [$ritem->id => 1],
            ])
            ->assertSessionHas('error');
    }

    public function test_cancel_allowed_from_draft_and_confirmed_without_receive(): void
    {
        $draft = $this->makeOrder('draft');
        $this->actingAs($this->purchasing())
            ->post(route('purchase-orders.cancel', $draft))
            ->assertSessionHas('success');

        $draft->refresh();
        $this->assertSame(PurchaseOrderStatus::Cancelled->value, $draft->status);

        $confirmed = $this->makeOrder('confirmed');
        $confirmedItem = $confirmed->items->first();

        $this->actingAs($this->purchasing())
            ->post(route('purchase-orders.receive', $confirmed), ['quantities' => [$confirmedItem->id => 5]])
            ->assertSessionHas('success');

        $this->actingAs($this->purchasing())
            ->post(route('purchase-orders.cancel', $confirmed))
            ->assertSessionHas('error');

        $confirmed->refresh();
        $this->assertSame(PurchaseOrderStatus::Confirmed->value, $confirmed->status);
    }

    public function test_destroy_only_works_on_draft(): void
    {
        $draft = $this->makeOrder('draft');
        $this->actingAs($this->purchasing())
            ->delete(route('purchase-orders.destroy', $draft))
            ->assertRedirect(route('purchase-orders.index'));

        $this->assertDatabaseMissing('purchase_orders', ['id' => $draft->id]);

        $confirmed = $this->makeOrder('confirmed');
        $this->actingAs($this->purchasing())
            ->delete(route('purchase-orders.destroy', $confirmed))
            ->assertSessionHas('error');
    }

    public function test_support_cannot_access_purchase_orders(): void
    {
        $this->actingAs($this->support())
            ->get(route('purchase-orders.index'))
            ->assertForbidden();
    }

    public function test_warehouse_manager_can_receive_but_not_create(): void
    {
        $this->actingAs($this->warehouseManager())
            ->get(route('purchase-orders.index'))->assertOk();

        $this->actingAs($this->warehouseManager())
            ->get(route('purchase-orders.create'))->assertForbidden();

        $this->actingAs($this->warehouseManager())
            ->post(route('purchase-orders.store'), [
                'supplier_id' => $this->makeSupplier()->id,
                'warehouse_id' => $this->makeWarehouse()->id,
                'items' => [['product_id' => $this->makeProduct()->id, 'quantity' => 1, 'unit_cost' => 1]],
            ])
            ->assertForbidden();
    }
}
```

- [ ] **Step 3: تشغيل الاختبارات**
  - Run: `php artisan test --filter=PurchaseTest` ثم `php artisan test --filter=SupplierTest`
  - Expected: كل الاختبارات تمر.

---

## Task 12: Documentation Updates

**Files:**
- Modify: `docs/DATABASE_SCHEMA.md` (تحديث purchase_orders + warehouse_id)
- Modify: `docs/PLAN.md` (Phase 4 → ✅ لشطر suppliers/orders/receive)

- [ ] **Step 1: `docs/DATABASE_SCHEMA.md`**
  - في المخطط (سطر 98-99) أضف `warehouse_id` لـ purchase_orders:
    `purchase_orders (id, order_number, supplier_id, status, order_date, expected_date, received_at, total, notes, created_by, warehouse_id)`
  - أضف سطر migration جديد في جدول §4: `2026_08_15_000001` | `purchase_orders: +warehouse_id`

- [ ] **Step 2: `docs/PLAN.md`**
  - في قسم Phase 4 (سطر 241) علّم:
    - `[x] Suppliers CRUD`
    - `[x] Purchase Orders + استلام → مخزون`
    - يبقى low-stock غير مُفعّل (لم يُبنَ).
  - أضف ملاحظة تنفيذ أوزة (مثل ملاحظات المراحل السابقة) تشرح الـ partial receive و `cancel_purchase_orders`.

---

## Task 13: Route/Test Verification

- [ ] **Step 1: `php artisan route:list --name=purchase-orders`**
  - Expected: 9 routes باسم ‏purchase-orders.* (index/create/store/show/edit/update/destroy/confirm/receive/cancel) بأسماء tmp permission صحيحة.

- [ ] **Step 2: `php artisan route:list --name=suppliers`**
  - Expected: 6 routes باسم suppliers.* (index/create/store/edit/update/destroy).

- [ ] **Step 3: `php artisan test` (كامل)**
  - Expected: كل الاختبارات الحالية (68) + الجديدة تمر — الصيغة الكلية ≥ 80.

- [ ] **Step 4: `php artisan migrate:status`**
  - Expected: migration `add_warehouse_id` مدرج ("Ran") بدون pending.

---

## Task 14: Final Smoke Test

- [ ] **Step 1: التأكد من `RbacSeeder` مُحدّث**
  - Run: `php artisan db:seed --class=RbacSeeder`
  - Expected: لا أخطاء؛ صلاحية `cancel_purchase_orders` موجودة.

- [ ] **Step 2: Smoke عبر `tinker` (تغطية فنية سريعة)**
  - Run (Batch في tinker):
    ```php
    $s = App\Models\ERP\Supplier::create(['name' => 'مورد اختبار', 'is_active' => 1]);
    $w = App\Models\ERP\Warehouse::create(['name' => 'WH-T', 'code' => 'WT1', 'is_active' => 1]);
    $p = App\Models\ERP\Product::create(['name' => 'P', 'sku' => 'SK-'.uniqid(), 'sale_price' => 10, 'is_active' => 1]);
    $o = App\Models\ERP\PurchaseOrder::create(['order_number' => 'PO-TEST', 'supplier_id' => $s->id, 'warehouse_id' => $w->id, 'status' => 'draft', 'order_date' => now()->toDateString(), 'created_by' => 1]);
    $o->items()->create(['product_id' => $p->id, 'quantity' => 10, 'unit_cost' => 5, 'total' => 50]);
    app(App\Services\ERP\PurchaseOrderService::class)->confirm($o);
    app(App\Services\ERP\PurchaseOrderService::class)->receive($o, [$o->items->first()->id => 4]);
    // تحقق: $p->stockItems()->value('quantity') === 4 ; $o->fresh()->status === 'confirmed'
    app(App\Services\ERP\PurchaseOrderService::class)->receive($o, [$o->items->first()->id => 6]);
    // تحقق: $o->fresh()->status === 'received' ; $p->stockItems()->value('quantity') === 10
    ```

- [ ] **Step 3: Smoke يدوي في المتصفح**
  - `php artisan serve` ثم جرب (دخول بـ purchasing@crm.test / password):
    1. إنشاء مورد → قائمة فارغة تعرضه.
    2. إنشاء أمر شراء بمورد + مستودع + صنف.
    3. تأكيد الأمر.
    4. استلام جزئي (4 من 10) → تحقق من `/stock` أن المنتج أصبح برصيد 4 في المستودع المختار.
    5. استلام باقي (6) → حالة order تصبح "مستلم كاملًا".
    6. محاولة إنشاء أمر بلا مورد/مستودع → أخطاء validation تظهر.
    7. دخول بـ support@crm.test → محاولة فتح `/purchase-orders` → 403.

---

## Self-Review ملاحظات (تُراجَع قبل التنفيذ)

- **تغطية المواصفة:** كل بند في المواصفة يقابله Task (الموديلات→2، enum→3، service→4، controllers→5-6، routes/RBAC→7، views→8-9، validation→10، tests→11، docs→12، verification→13-14).
- **الاتساق:** `receive()` لا يقبل مستودعًا؛ `cancel` له صلاحية جديدة؛ `destroy vs cancel` منعزلان؛ `supplier_id`/`warehouse_id` مطلوبان دوليا.
- **استثناء Rollback:** في `test_receive_rejects_zero_or_negative` الكمية 0 تُتخطى (وليس error) — كما هو مبين في `PurchaseOrderService::receive` (continue). هذا مقصود لأن النموذج يرسل inputs للبنود غير المستلمة.