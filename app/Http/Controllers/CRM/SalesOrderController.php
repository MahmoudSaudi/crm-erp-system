<?php

namespace App\Http\Controllers\CRM;

use App\Enums\SalesOrderStatus;
use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CRM\StoreSalesOrderRequest;
use App\Http\Requests\CRM\UpdateSalesOrderRequest;
use App\Models\CRM\Customer;
use App\Models\CRM\SalesOrder;
use App\Models\ERP\Product;
use App\Models\ERP\Warehouse;
use App\Services\AuditLogger;
use App\Services\CRM\SalesOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class SalesOrderController extends Controller
{
    public function __construct(private readonly SalesOrderService $orderService)
    {
    }

    public function index(Request $request): View
    {
        $orders = SalesOrder::query()
            ->with(['customer:id,name', 'creator:id,name'])
            ->search($request->get('search'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->get('status')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('crm.sales-orders.index', [
            'orders' => $orders,
            'statuses' => SalesOrderStatus::list(),
            'activeStatus' => $request->get('status'),
        ]);
    }

    public function create(): View
    {
        return view('crm.sales-orders.create', [
            'customers' => Customer::orderBy('name')->get(['id', 'name']),
            'products' => Product::where('is_active', true)->orderBy('name')->get(['id', 'name', 'sku', 'sale_price', 'min_stock']),
            'warehouses' => Warehouse::active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreSalesOrderRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $items = $this->validatedItems($request);

        $order = SalesOrder::create([
            'order_number' => $this->nextNumber(),
            'customer_id' => $data['customer_id'],
            'status' => SalesOrderStatus::Draft->value,
            'order_date' => $data['order_date'] ?? now()->toDateString(),
            'delivery_date' => $data['delivery_date'] ?? null,
            'discount_amount' => $data['discount_amount'] ?? 0,
            'shipping_amount' => $data['shipping_amount'] ?? 0,
            'notes' => $data['notes'] ?? null,
            'created_by' => auth()->id(),
        ]);

        foreach ($items as $line) {
            $product = Product::find($line['product_id']);
            $total = round((float) $line['quantity'] * (float) $line['unit_price'], 2);

            $order->items()->create([
                'product_id' => $product?->id,
                'product_name' => $product?->name,
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'discount' => 0,
                'total' => $total,
            ]);
        }

        $this->orderService->recalculateTotals($order);

        ActivityController::autoLog('note', 'إنشاء أمر بيع', $order, ['number' => $order->order_number, 'total' => $order->total]);
        AuditLogger::log('created', $order, null, $order->toArray());

        return redirect()->route('sales-orders.show', $order)->with('success', 'تم إنشاء أمر البيع بنجاح');
    }

    public function show(SalesOrder $salesOrder): View
    {
        $salesOrder->load(['customer', 'items.product:id,name,sku', 'creator:id,name', 'invoice:id,invoice_number,status']);

        return view('crm.sales-orders.show', ['order' => $salesOrder]);
    }

    public function edit(SalesOrder $salesOrder): View
    {
        $salesOrder->load('items');

        return view('crm.sales-orders.edit', [
            'order' => $salesOrder,
            'customers' => Customer::orderBy('name')->get(['id', 'name']),
            'products' => Product::where('is_active', true)->orderBy('name')->get(['id', 'name', 'sku', 'sale_price', 'min_stock']),
            'warehouses' => Warehouse::active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(UpdateSalesOrderRequest $request, SalesOrder $salesOrder): RedirectResponse
    {
        if ($salesOrder->status !== SalesOrderStatus::Draft->value) {
            return back()->with('error', 'لا يمكن تعديل أمر تم تأكيده أو تنفيذه');
        }

        $data = $request->validated();
        $items = $this->validatedItems($request);

        $salesOrder->update([
            'customer_id' => $data['customer_id'],
            'order_date' => $data['order_date'] ?? now()->toDateString(),
            'delivery_date' => $data['delivery_date'] ?? null,
            'discount_amount' => $data['discount_amount'] ?? 0,
            'shipping_amount' => $data['shipping_amount'] ?? 0,
            'notes' => $data['notes'] ?? null,
        ]);

        $salesOrder->items()->delete();
        foreach ($items as $line) {
            $product = Product::find($line['product_id']);
            $salesOrder->items()->create([
                'product_id' => $product?->id,
                'product_name' => $product?->name,
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'discount' => 0,
                'total' => round((float) $line['quantity'] * (float) $line['unit_price'], 2),
            ]);
        }

        $this->orderService->recalculateTotals($salesOrder);
        AuditLogger::log('updated', $salesOrder, null, $salesOrder->fresh()->toArray());

        return redirect()->route('sales-orders.show', $salesOrder)->with('success', 'تم تحديث أمر البيع');
    }

    public function destroy(SalesOrder $salesOrder): RedirectResponse
    {
        if ($salesOrder->status !== SalesOrderStatus::Draft->value) {
            return back()->with('error', 'لا يمكن حذف أمر تم تأكيده');
        }

        AuditLogger::log('deleted', $salesOrder, $salesOrder->toArray(), null);
        $salesOrder->delete();

        return redirect()->route('sales-orders.index')->with('success', 'تم حذف أمر البيع');
    }

    public function confirm(SalesOrder $salesOrder): RedirectResponse
    {
        try {
            $this->orderService->confirm($salesOrder);
        } catch (InsufficientStockException | RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "تم تأكيد الأمر وخصم المخزون تلقائيًا");
    }

    public function fulfill(SalesOrder $salesOrder): RedirectResponse
    {
        try {
            $this->orderService->fulfill($salesOrder);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'تم تنفيذ أمر البيع');
    }

    public function cancel(SalesOrder $salesOrder): RedirectResponse
    {
        try {
            $this->orderService->cancel($salesOrder);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'تم إلغاء أمر البيع');
    }

    private function nextNumber(): string
    {
        $max = SalesOrder::withTrashed()->max('id') ?? 0;

        return 'SO-'.str_pad((string) ($max + 1), 6, '0', STR_PAD_LEFT);
    }

    private function validatedItems(Request $request): array
    {
        $items = collect($request->get('items', []))->filter(fn ($line) => ! empty($line['product_id']) && (float) ($line['quantity'] ?? 0) > 0);

        return $items->values()->map(function ($line) {
            return [
                'product_id' => $line['product_id'],
                'quantity' => (float) $line['quantity'],
                'unit_price' => (float) ($line['unit_price'] ?? 0),
            ];
        })->all();
    }
}
