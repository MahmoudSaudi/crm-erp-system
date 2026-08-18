<?php

namespace App\Http\Controllers\ERP;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ERP\StoreInvoiceRequest;
use App\Models\CRM\Customer;
use App\Models\ERP\Invoice;
use App\Services\AuditLogger;
use App\Services\ERP\InvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class InvoiceController extends Controller
{
    public function __construct(private readonly InvoiceService $invoiceService)
    {
    }

    public function index(Request $request): View
    {
        $invoices = Invoice::query()
            ->with(['customer:id,name', 'salesOrder:id,order_number'])
            ->search($request->get('search'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->get('status')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $totals = [
            'total' => Invoice::whereNot('status', InvoiceStatus::Cancelled->value)->sum('total'),
            'paid' => Invoice::whereNot('status', InvoiceStatus::Cancelled->value)->sum('paid_amount'),
            'due' => Invoice::whereNot('status', InvoiceStatus::Cancelled->value)->get()->sum(fn ($i) => $i->dueAmount()),
        ];

        return view('erp.invoices.index', [
            'invoices' => $invoices,
            'statuses' => InvoiceStatus::list(),
            'activeStatus' => $request->get('status'),
            'totals' => $totals,
        ]);
    }

    public function create(Request $request): View
    {
        return view('erp.invoices.create', [
            'customers' => Customer::orderBy('name')->get(['id', 'name']),
            'orders' => \App\Models\CRM\SalesOrder::with('customer:id,name')
                ->where('status', 'confirmed')
                ->whereDoesntHave('invoice')
                ->orderBy('order_number')
                ->get(['id', 'order_number', 'customer_id', 'total']),
            'selectedOrder' => $request->get('order_id'),
        ]);
    }

    public function store(StoreInvoiceRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $order = \App\Models\CRM\SalesOrder::with(['items', 'customer'])->findOrFail($data['order_id']);

        try {
            $invoice = $this->invoiceService->createFromOrder($order, [
                'issue_date' => $data['issue_date'] ?? now()->toDateString(),
                'due_date' => $data['due_date'] ?? now()->addDays(15)->toDateString(),
                'notes' => $data['notes'] ?? null,
            ]);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        AuditLogger::log('created', $invoice, null, $invoice->toArray());

        return redirect()->route('invoices.show', $invoice)->with('success', 'تم إنشاء الفاتورة بنجاح');
    }

    public function show(Invoice $invoice): View
    {
        $invoice->load(['customer', 'salesOrder:id,order_number', 'items.product:id,name,sku', 'payments.creator:id,name', 'creator:id,name']);

        return view('erp.invoices.show', [
            'invoice' => $invoice,
            'methods' => \App\Enums\PaymentMethod::list(),
        ]);
    }

    public function print(Invoice $invoice): View
    {
        $invoice->load(['customer', 'items.product:id,name,sku']);

        return view('erp.invoices.print', ['invoice' => $invoice]);
    }

    public function send(Invoice $invoice): RedirectResponse
    {
        $invoice->update(['status' => InvoiceStatus::Sent->value, 'sent_at' => now()]);

        AuditLogger::log('sent', $invoice, null, ['status' => InvoiceStatus::Sent->value]);

        return back()->with('success', 'تم تحديد الفاتورة كمرسلة');
    }

    public function destroy(Invoice $invoice): RedirectResponse
    {
        AuditLogger::log('deleted', $invoice, $invoice->toArray(), null);
        $invoice->delete();

        return redirect()->route('invoices.index')->with('success', 'تم حذف الفاتورة');
    }
}
