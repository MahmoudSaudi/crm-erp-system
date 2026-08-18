<?php

namespace App\Http\Controllers\ERP;

use App\Enums\PurchaseOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ERP\StorePurchaseOrderRequest;
use App\Http\Requests\ERP\UpdatePurchaseOrderRequest;
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
        return view('erp.purchase-orders.create', $this->formData());
    }

    public function store(StorePurchaseOrderRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $items = $request->validated('items');

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
            $order->items()->create([
                'product_id' => $line['product_id'],
                'quantity' => (float) $line['quantity'],
                'unit_cost' => (float) ($line['unit_cost'] ?? 0),
                'total' => round((float) $line['quantity'] * (float) ($line['unit_cost'] ?? 0), 2),
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

        return view('erp.purchase-orders.edit', ['order' => $purchaseOrder] + $this->formData());
    }

    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if ($purchaseOrder->status !== PurchaseOrderStatus::Draft->value) {
            return back()->with('error', 'لا يمكن تعديل أمر تم تأكيده أو استلامه');
        }

        $data = $request->validated();
        $items = $request->validated('items');

        $purchaseOrder->update([
            'supplier_id' => $data['supplier_id'],
            'warehouse_id' => $data['warehouse_id'],
            'order_date' => $data['order_date'] ?? now()->toDateString(),
            'expected_date' => $data['expected_date'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        $purchaseOrder->items()->delete();
        foreach ($items as $line) {
            $purchaseOrder->items()->create([
                'product_id' => $line['product_id'],
                'quantity' => (float) $line['quantity'],
                'unit_cost' => (float) ($line['unit_cost'] ?? 0),
                'total' => round((float) $line['quantity'] * (float) ($line['unit_cost'] ?? 0), 2),
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

    private function formData(): array
    {
        return [
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'warehouses' => Warehouse::active()->orderBy('name')->get(['id', 'name']),
            'products' => Product::where('is_active', true)->orderBy('name')->get(['id', 'name', 'sku', 'purchase_cost', 'min_stock']),
        ];
    }
}