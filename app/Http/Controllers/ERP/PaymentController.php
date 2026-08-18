<?php

namespace App\Http\Controllers\ERP;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ERP\StorePaymentRequest;
use App\Models\CRM\Customer;
use App\Models\ERP\Invoice;
use App\Models\ERP\Payment;
use App\Services\AuditLogger;
use App\Services\ERP\InvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class PaymentController extends Controller
{
    public function __construct(private readonly InvoiceService $invoiceService)
    {
    }

    public function index(Request $request): View
    {
        $payments = Payment::query()
            ->with(['customer:id,name', 'invoice:id,invoice_number', 'creator:id,name'])
            ->search($request->get('search'))
            ->when($request->filled('method'), fn ($q) => $q->where('method', $request->get('method')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('erp.payments.index', [
            'payments' => $payments,
            'methods' => \App\Enums\PaymentMethod::list(),
            'activeMethod' => $request->get('method'),
        ]);
    }

    public function create(Request $request): View
    {
        $invoices = Invoice::with('customer:id,name')
            ->where('status', '!=', InvoiceStatus::Cancelled->value)
            ->get()
            ->filter(fn (Invoice $invoice) => $invoice->dueAmount() > 0);

        return view('erp.payments.create', [
            'customers' => Customer::orderBy('name')->get(['id', 'name']),
            'invoices' => $invoices,
            'methods' => \App\Enums\PaymentMethod::list(),
            'selectedInvoice' => $request->get('invoice_id'),
        ]);
    }

    public function store(StorePaymentRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $invoice = Invoice::findOrFail($data['invoice_id']);

        try {
            $payment = $this->invoiceService->recordPayment($invoice, $data);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('invoices.show', $invoice)->with('success', 'تم تسجيل الدفعة وتحديث الفاتورة');
    }
}
