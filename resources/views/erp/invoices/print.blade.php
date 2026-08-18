<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>فاتورة {{ $invoice->invoice_number }}</title>
    <style>
        body { font-family: "Segoe UI", Tahoma, Arial, sans-serif; color: #0f172a; margin: 0; padding: 40px; background: #fff; }
        .invoice-box { max-width: 800px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #e2e8f0; padding-bottom: 24px; }
        .title { font-size: 28px; font-weight: 800; color: #4338ca; margin: 0 0 4px; }
        .number { font-family: Consolas, monospace; color: #64748b; font-size: 14px; }
        .meta { font-size: 13px; color: #475569; line-height: 1.8; }
        .party { margin-top: 24px; display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .card { border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; }
        .card h4 { margin: 0 0 6px; font-size: 12px; color: #94a3b8; text-transform: uppercase; }
        .card .value { font-size: 15px; font-weight: 700; }
        .card .sub { font-size: 13px; color: #475569; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 24px; font-size: 14px; }
        table.items th { background: #f1f5f9; text-align: right; padding: 10px 12px; font-size: 12px; color: #475569; }
        table.items td { padding: 10px 12px; border-bottom: 1px solid #f1f5f9; }
        table.items tfoot td { font-weight: 700; padding-top: 12px; }
        .grand { font-size: 18px; color: #4338ca; }
        .status { display: inline-block; padding: 3px 12px; border-radius: 9999px; font-size: 12px; font-weight: 700; }
        .status.sent, .status.paid { background: #d1fae5; color: #047857; }
        .status.draft { background: #e2e8f0; color: #475569; }
        .status.partial { background: #fef3c7; color: #b45309; }
        .status.overdue { background: #fee2e2; color: #b91c1c; }
        .notes { margin-top: 24px; border-top: 1px dashed #e2e8f0; padding-top: 16px; font-size: 13px; color: #64748b; }
        .footer { margin-top: 32px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 16px; }
        @media print {
            body { padding: 20px; }
        }
    </style>
</head>
<body>
    <div class="invoice-box">
        <div class="header">
            <div>
                <h1 class="title">فاتورة</h1>
                <p class="number" dir="ltr">{{ $invoice->invoice_number }}</p>
            </div>
            <div class="meta">
                <p>تاريخ الإصدار: <strong>{{ $invoice->issue_date?->format('Y-m-d') }}</strong></p>
                <p>تاريخ الاستحقاق: <strong>{{ $invoice->due_date?->format('Y-m-d') }}</strong></p>
                <span class="status {{ $invoice->status }}">{{ \App\Enums\InvoiceStatus::from($invoice->status)->label() }}</span>
            </div>
        </div>

        <div class="party">
            <div class="card">
                <h4>المُفوتر إليه</h4>
                <p class="value">{{ $invoice->customer?->name ?? '—' }}</p>
                @if ($invoice->customer?->phone)
                    <p class="sub" dir="ltr">{{ $invoice->customer->phone }}</p>
                @endif
                @if ($invoice->customer?->email)
                    <p class="sub" dir="ltr">{{ $invoice->customer->email }}</p>
                @endif
                @if ($invoice->customer?->address)
                    <p class="sub">{{ $invoice->customer->address }}</p>
                @endif
            </div>
            <div class="card">
                <h4>الشركة</h4>
                <p class="value">{{ config('app.name', 'شركتي') }}</p>
                <p class="sub">فاتورة مبيعات</p>
                @if ($invoice->salesOrder)
                    <p class="sub">مرجع: <span dir="ltr">{{ $invoice->salesOrder->order_number }}</span></p>
                @endif
            </div>
        </div>

        <table class="items">
            <thead>
                <tr>
                    <th>المنتج</th>
                    <th>الكمية</th>
                    <th>سعر الوحدة</th>
                    <th>الإجمالي</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoice->items as $item)
                    <tr>
                        <td>{{ $item->product_name }}</td>
                        <td>{{ (float) $item->quantity }}</td>
                        <td>{{ number_format((float) $item->unit_price, 2) }} ₪</td>
                        <td>{{ number_format((float) $item->total, 2) }} ₪</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr><td colspan="3">الإجمالي</td><td class="grand">{{ number_format((float) $invoice->total, 2) }} ₪</td></tr>
                <tr><td colspan="3">المدفوع</td><td>{{ number_format((float) $invoice->paid_amount, 2) }} ₪</td></tr>
                <tr><td colspan="3">المستحق</td><td>{{ number_format((float) $invoice->dueAmount(), 2) }} ₪</td></tr>
            </tfoot>
        </table>

        @if ($invoice->notes)
            <div class="notes"><strong>ملاحظات:</strong> {{ $invoice->notes }}</div>
        @endif

        <div class="footer">تم إنشاء هذه الفاتورة إلكترونيًا بواسطة {{ config('app.name', 'نظام CRM') }} · {{ now()->format('Y-m-d H:i') }}</div>
    </div>
</body>
</html>