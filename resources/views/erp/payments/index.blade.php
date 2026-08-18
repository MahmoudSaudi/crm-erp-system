<x-app-layout>
    <x-slot name="title">{{ __('المدفوعات') }}</x-slot>

    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('المدفوعات') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">سجل الدفعات المستلمة من العملاء</p>
            </div>
            @can('permission.create_payments')
                <a href="{{ route('payments.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                    {{ __('تسجيل دفعة') }}
                </a>
            @endcan
        </div>

        {{-- Filters --}}
        <form method="GET" action="{{ route('payments.index') }}" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <x-input-label for="search" value="بحث" />
                    <x-text-input id="search" name="search" type="text" class="mt-1 block w-full" value="{{ request('search') }}" placeholder="اسم العميل أو رقم الفاتورة..." />
                </div>
                <div>
                    <x-input-label for="method" value="طريقة الدفع" />
                    <select id="method" name="method" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                        <option value="">كل الطرق</option>
                        @foreach ($methods as $value => $label)
                            <option value="{{ $value }}" @selected($activeMethod === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                        {{ __('تصفية') }}
                    </button>
                    @if (request()->hasAny(['search', 'method']))
                        <a href="{{ route('payments.index') }}" class="ms-2 inline-flex items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-800">{{ __('مسح') }}</a>
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
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">العميل</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">الفاتورة</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">المبلغ</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">الطريقة</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">التاريخ</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">سُجل بواسطة</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">مرجع</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($payments as $payment)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="px-4 py-3 text-sm font-medium text-slate-900 dark:text-white">
                                    @if ($payment->customer)
                                        <a href="{{ route('customers.show', $payment->customer) }}" class="hover:text-indigo-600 dark:hover:text-indigo-400">{{ $payment->customer->name }}</a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    @if ($payment->invoice)
                                        <a href="{{ route('invoices.show', $payment->invoice) }}" class="font-medium text-indigo-600 dark:text-indigo-400" dir="ltr">{{ $payment->invoice->invoice_number }}</a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm font-semibold text-emerald-600 dark:text-emerald-400">{{ number_format((float) $payment->amount, 2) }} ₪</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                        {{ \App\Enums\PaymentMethod::from($payment->method)->label() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">{{ $payment->paid_at?->format('Y-m-d') }}</td>
                                <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">{{ $payment->creator?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm text-slate-500 dark:text-slate-400">{{ $payment->reference ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <x-empty-state icon="chart" title="{{ __('لا توجد دفعات بعد') }}" :description="__('لم تُستلم أي دفعات بعد. تُعرض هنا المدفوعات الواردة من العملاء والفواتير.')" />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($payments->hasPages())
                <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-700">{{ $payments->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>