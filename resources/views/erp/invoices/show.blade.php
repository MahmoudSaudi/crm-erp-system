<x-app-layout>
    <x-slot name="title">{{ __('فاتورة') }} {{ $invoice->invoice_number }}</x-slot>

    @php
        $status = \App\Enums\InvoiceStatus::from($invoice->status);
        $due = $invoice->dueAmount();
    @endphp

    <div class="mx-auto max-w-5xl space-y-6">
        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <h2 class="text-2xl font-bold text-slate-900 dark:text-white"><span class="font-mono" dir="ltr">{{ $invoice->invoice_number }}</span></h2>
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $status?->color() }}">{{ $status?->label() }}</span>
                </div>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">أُصدرت في {{ $invoice->issue_date?->format('Y-m-d') }} @if ($invoice->due_date) · تستحق في {{ $invoice->due_date->format('Y-m-d') }} @endif</p>
                @if ($invoice->salesOrder)
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        أمر البيع: <a href="{{ route('sales-orders.show', $invoice->salesOrder) }}" class="font-medium text-indigo-600 dark:text-indigo-400" dir="ltr">{{ $invoice->salesOrder->order_number }}</a>
                    </p>
                @endif
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @can('permission.send_invoices')
                    @if (in_array($invoice->status, ['draft', 'sent']))
                        <form method="POST" action="{{ route('invoices.send', $invoice) }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                                {{ $invoice->status === 'sent' ? 'إعادة إرسال' : 'تحديد كمرسلة' }}
                            </button>
                        </form>
                    @endif
                @endcan
                @can('permission.print_invoices')
                    <a href="{{ route('invoices.print', $invoice) }}" target="_blank" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-800">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
                        طباعة
                    </a>
                @endcan
                @can('permission.view_invoices')
                    <form method="POST" action="{{ route('invoices.destroy', $invoice) }}" onsubmit="return confirm('حذف الفاتورة؟')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="inline-flex items-center rounded-lg border border-rose-200 px-4 py-2 text-sm font-medium text-rose-600 hover:bg-rose-50 dark:border-rose-500/30 dark:hover:bg-rose-500/10">حذف</button>
                    </form>
                @endcan
            </div>
        </div>

        {{-- Summary --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <p class="text-sm text-slate-500 dark:text-slate-400">الإجمالي</p>
                <p class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">{{ number_format((float) $invoice->total, 2) }} ₪</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <p class="text-sm text-slate-500 dark:text-slate-400">المدفوع</p>
                <p class="mt-1 text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ number_format((float) $invoice->paid_amount, 2) }} ₪</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <p class="text-sm text-slate-500 dark:text-slate-400">المستحق</p>
                <p class="mt-1 text-2xl font-bold text-rose-600 dark:text-rose-400">{{ number_format((float) $due, 2) }} ₪</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Items --}}
            <div class="lg:col-span-2 rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-700">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">الأصناف</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                        <thead class="bg-slate-50 dark:bg-slate-800/60">
                            <tr>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">المنتج</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">الكمية</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">سعر الوحدة</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">الإجمالي</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($invoice->items as $item)
                                <tr>
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-slate-900 dark:text-white">{{ $item->product_name }}</div>
                                        @if ($item->product)
                                            <div class="text-xs text-slate-400 dark:text-slate-500" dir="ltr">{{ $item->product->sku }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">{{ (float) $item->quantity }}</td>
                                    <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">{{ number_format((float) $item->unit_price, 2) }} ₪</td>
                                    <td class="px-4 py-3 text-sm font-semibold text-slate-700 dark:text-slate-200">{{ number_format((float) $item->total, 2) }} ₪</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($invoice->notes)
                    <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-700">
                        <p class="text-sm text-slate-600 dark:text-slate-300">{{ $invoice->notes }}</p>
                    </div>
                @endif
            </div>

            {{-- Payments --}}
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-700">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">المدفوعات</h3>
                </div>

                @if ($due > 0)
                    @can('permission.create_payments')
                        <form method="POST" action="{{ route('payments.store') }}" class="border-b border-slate-200 p-4 dark:border-slate-700">
                            @csrf
                            <input type="hidden" name="invoice_id" value="{{ $invoice->id }}" />
                            <div class="grid grid-cols-2 gap-2">
                                <div class="col-span-2">
                                    <x-input-label for="amount" value="المبلغ" />
                                    <x-text-input id="amount" name="amount" type="number" step="0.01" min="0.01" :max="$due" class="mt-1 block w-full" placeholder="0.00" required />
                                </div>
                                <div class="col-span-2">
                                    <x-input-label for="method" value="طريقة الدفع" />
                                    <select id="method" name="method" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                                        @foreach ($methods as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-span-2">
                                    <x-input-label for="paid_at" value="تاريخ الدفع" />
                                    <x-text-input id="paid_at" name="paid_at" type="date" class="mt-1 block w-full" value="{{ now()->toDateString() }}" />
                                </div>
                            </div>
                            <x-primary-button class="mt-3 w-full justify-center">{{ __('تسجيل دفعة') }}</x-primary-button>
                        </form>
                    @endcan
                @endif

                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($invoice->payments as $payment)
                        <div class="flex items-center justify-between px-5 py-3">
                            <div>
                                <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ number_format((float) $payment->amount, 2) }} ₪</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ \App\Enums\PaymentMethod::from($payment->method)->label() }}
                                    @if ($payment->creator)
                                        · {{ $payment->creator->name }}
                                    @endif
                                </p>
                            </div>
                            <span class="text-xs text-slate-400 dark:text-slate-500">{{ $payment->paid_at?->format('Y-m-d') }}</span>
                        </div>
                    @empty
                        <p class="px-5 py-8 text-center text-sm text-slate-400 dark:text-slate-500">لا توجد دفعات بعد</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-app-layout>