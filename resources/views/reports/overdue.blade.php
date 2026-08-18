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