<?php

namespace App\Services\ERP;

use App\Enums\InvoiceStatus;
use App\Models\CRM\SalesOrder;
use App\Models\ERP\Invoice;
use App\Models\ERP\InvoiceItem;
use App\Models\ERP\Payment;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InvoiceService
{
    public const TAX_RATE = 0;

    /**
     * Creates an invoice from a sales order, copying its lines.
     */
    public function createFromOrder(SalesOrder $order, ?array $overrides = null): Invoice
    {
        if ($order->status !== \App\Enums\SalesOrderStatus::Confirmed->value) {
            throw new RuntimeException('لا يمكن إنشاء فاتورة إلا من أمر بيع مؤكد');
        }

        if ($order->invoice) {
            throw new RuntimeException('هذا الأمر لديه فاتورة بالفعل');
        }

        return DB::transaction(function () use ($order, $overrides) {
            $data = array_merge([
                'invoice_number' => $this->nextNumber(),
                'customer_id' => $order->customer_id,
                'sales_order_id' => $order->id,
                'status' => InvoiceStatus::Draft->value,
                'issue_date' => now()->toDateString(),
                'due_date' => now()->addDays(15)->toDateString(),
                'subtotal' => $order->subtotal,
                'discount_amount' => $order->discount_amount,
                'tax_amount' => $order->tax_amount,
                'total' => $order->total,
                'paid_amount' => 0,
                'created_by' => auth()->id(),
            ], $overrides ?? []);

            $invoice = Invoice::create($data);

            foreach ($order->items as $item) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'product_id' => $item->product_id,
                    'description' => $item->product_name ?? $item->product?->name ?? 'منتج',
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'total' => $item->total,
                ]);
            }

            AuditLogger::log('created_from_order', $invoice, null, ['sales_order_id' => $order->id]);

            return $invoice;
        });
    }

    /**
     * Records a payment against an invoice and recomputes its status + paid amount.
     */
    public function recordPayment(Invoice $invoice, array $data): Payment
    {
        $amount = (float) $data['amount'];
        $due = $invoice->dueAmount();

        if ($amount <= 0) {
            throw new RuntimeException('مبلغ الدفعة يجب أن يكون أكبر من صفر');
        }

        if ($amount > $due) {
            throw new RuntimeException("المبلغ المدفوع أكبر من المستحق ({$due})");
        }

        return DB::transaction(function () use ($invoice, $amount, $data) {
            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'customer_id' => $invoice->customer_id,
                'amount' => $amount,
                'method' => $data['method'] ?? 'cash',
                'reference' => $data['reference'] ?? null,
                'status' => 'completed',
                'paid_at' => $data['paid_at'] ?? now(),
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $this->recomputeStatus($invoice);

            AuditLogger::log('payment_recorded', $payment, null, $payment->toArray());

            return $payment;
        });
    }

    /**
     * Recomputes paid_amount and status based on recorded payments.
     */
    public function recomputeStatus(Invoice $invoice): Invoice
    {
        $paid = (float) $invoice->payments()
            ->where('status', 'completed')
            ->sum('amount');

        $total = (float) $invoice->total;
        $due = max(0, $total - $paid);

        $status = match (true) {
            $invoice->status === InvoiceStatus::Cancelled->value => InvoiceStatus::Cancelled->value,
            $total > 0 && $due <= 0 => InvoiceStatus::Paid->value,
            $paid > 0 => InvoiceStatus::Partial->value,
            $invoice->due_date && $invoice->due_date->isPast() && $invoice->status !== InvoiceStatus::Draft->value => InvoiceStatus::Overdue->value,
            default => $invoice->status ?: InvoiceStatus::Draft->value,
        };

        $invoice->update([
            'paid_amount' => $paid,
            'status' => $status,
        ]);

        return $invoice->fresh();
    }

    public function nextNumber(): string
    {
        $max = Invoice::withTrashed()->max('id') ?? 0;

        return 'INV-'.str_pad((string) ($max + 1), 6, '0', STR_PAD_LEFT);
    }
}
