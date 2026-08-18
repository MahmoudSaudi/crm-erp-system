<x-app-layout>
    <x-slot name="title">{{ __('الفواتير') }}</x-slot>

    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('الفواتير') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">إنشاء الفواتير وتتبع المدفوعات والحالات</p>
            </div>
            @can('permission.create_invoices')
                <a href="{{ route('invoices.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                    {{ __('إنشاء فاتورة') }}
                </a>
            @endcan
        </div>

        {{-- Summary cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <p class="text-sm text-slate-500 dark:text-slate-400">إجمالي الفواتير</p>
                <p class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">{{ number_format((float) $totals['total'], 2) }} ₪</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <p class="text-sm text-slate-500 dark:text-slate-400">المُحصّل</p>
                <p class="mt-1 text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ number_format((float) $totals['paid'], 2) }} ₪</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <p class="text-sm text-slate-500 dark:text-slate-400">المستحق</p>
                <p class="mt-1 text-2xl font-bold text-rose-600 dark:text-rose-400">{{ number_format((float) $totals['due'], 2) }} ₪</p>
            </div>
        </div>

        {{-- Filters --}}
        <form method="GET" action="{{ route('invoices.index') }}" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <x-input-label for="search" value="بحث" />
                    <x-text-input id="search" name="search" type="text" class="mt-1 block w-full" value="{{ request('search') }}" placeholder="رقم الفاتورة أو اسم العميل..." />
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
                <div class="flex items-end gap-2">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                        {{ __('تصفية') }}
                    </button>
                    @if (request()->hasAny(['search', 'status']))
                        <a href="{{ route('invoices.index') }}" class="ms-2 inline-flex items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-800">{{ __('مسح') }}</a>
                    @endif
                </div>
            </div>
        </form>

        {{-- Table --}}
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-800/60">
                        <tr>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">رقم الفاتورة</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">العميل</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">أمر البيع</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">تاريخ الإصدار</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">الإجمالي</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">المدفوع</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">الحالة</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($invoices as $invoice)
                            @php $invoiceStatus = \App\Enums\InvoiceStatus::from($invoice->status); @endphp
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="px-4 py-3">
                                    <a href="{{ route('invoices.show', $invoice) }}" class="font-medium text-indigo-600 hover:text-indigo-700 dark:text-indigo-400" dir="ltr">{{ $invoice->invoice_number }}</a>
                                </td>
                                <td class="px-4 py-3 text-sm font-medium text-slate-900 dark:text-white">{{ $invoice->customer?->name }}</td>
                                <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">
                                    @if ($invoice->salesOrder)
                                        <a href="{{ route('sales-orders.show', $invoice->salesOrder) }}" class="font-medium text-indigo-600 dark:text-indigo-400" dir="ltr">{{ $invoice->salesOrder->order_number }}</a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">{{ $invoice->issue_date?->format('Y-m-d') }}</td>
                                <td class="px-4 py-3 text-sm font-semibold text-slate-700 dark:text-slate-200">{{ number_format((float) $invoice->total, 2) }} ₪</td>
                                <td class="px-4 py-3 text-sm text-emerald-600 dark:text-emerald-400">{{ number_format((float) $invoice->paid_amount, 2) }} ₪</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $invoiceStatus?->color() }}">{{ $invoiceStatus?->label() }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-1">
                                        <a href="{{ route('invoices.show', $invoice) }}" class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-300" title="عرض">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0zm-9 0a9 9 0 0118 0 9 9 0 01-18 0z" /></svg>
                                        </a>
                                        @can('permission.print_invoices')
                                            <a href="{{ route('invoices.print', $invoice) }}" target="_blank" class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-300" title="طباعة">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
                                            </a>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8">
                                    <x-empty-state icon="chart" title="{{ __('لا توجد فواتير بعد') }}" :description="__('أنشئ فواتير من أوامر البيع المؤكدة.')" />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($invoices->hasPages())
                <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-700">{{ $invoices->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>