<x-app-layout>
    <x-slot name="title">{{ __('تقرير المبيعات') }}</x-slot>

    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-4">
                <a href="{{ route('reports.index') }}" class="flex items-center justify-center p-2 rounded-lg text-slate-500 bg-white border border-slate-200 hover:bg-slate-50 hover:text-slate-900 transition-colors dark:bg-slate-800 dark:border-slate-700 dark:text-slate-400 dark:hover:bg-slate-700 dark:hover:text-white" title="العودة للتقارير">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </a>
                <div>
                    <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('تقرير المبيعات') }}</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('ملخص المبيعات والتحصيل والمستحق خلال الفترة') }}</p>
                </div>
            </div>
            <form method="GET" action="{{ route('reports.sales') }}" class="flex items-center gap-2">
                <input type="month" name="month" value="{{ $month }}" class="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900">
                <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">{{ __('تصفية') }}</button>
                @if ($month)
                    <a href="{{ route('reports.sales') }}" class="text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400">{{ __('إزالة التصفية') }}</a>
                @endif
            </form>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('طلبات البيع') }}</p>
                <p class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">{{ number_format($summary['ordersCount']) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('الإيرادات') }}</p>
                <p class="mt-1 text-2xl font-bold text-indigo-600 dark:text-indigo-400">{{ number_format($summary['revenue'], 2) }} ₪</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('المُحصّل') }}</p>
                <p class="mt-1 text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ number_format($summary['collected'], 2) }} ₪</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('المستحق') }}</p>
                <p class="mt-1 text-2xl font-bold text-rose-600 dark:text-rose-400">{{ number_format($summary['due'], 2) }} ₪</p>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">{{ __('المبيعات شهريًا') }}</h3>
                <div class="flex items-center gap-4 text-xs font-medium text-slate-500 dark:text-slate-400">
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-indigo-500"></span> {{ __('المبيعات') }}</span>
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span> {{ __('التحصيل') }}</span>
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span> {{ __('المصروفات') }}</span>
                </div>
            </div>
            <div class="mt-4 h-72">
                <canvas id="salesChart"></canvas>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">{{ __('أعلى العملاء') }}</h3>
                <div class="mt-4 space-y-3">
                    @forelse ($summary['topCustomers'] as $customer)
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ $customer['name'] }}</span>
                            <span class="text-sm font-bold text-indigo-600 dark:text-indigo-400">{{ number_format($customer['total'], 2) }} ₪</span>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('لا توجد بيانات') }}</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">{{ __('آخر طلبات البيع') }}</h3>
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-right text-xs text-slate-500 dark:text-slate-400">
                                <th class="pb-2 font-medium">{{ __('الرقم') }}</th>
                                <th class="pb-2 font-medium">{{ __('العميل') }}</th>
                                <th class="pb-2 font-medium">{{ __('التاريخ') }}</th>
                                <th class="pb-2 font-medium">{{ __('الإجمالي') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @forelse ($summary['recentOrders'] as $order)
                                <tr>
                                    <td class="py-2 font-medium text-slate-900 dark:text-white">{{ $order->order_number }}</td>
                                    <td class="py-2 text-slate-600 dark:text-slate-300">{{ $order->customer?->name ?? '—' }}</td>
                                    <td class="py-2 text-slate-600 dark:text-slate-300">{{ $order->order_date->format('Y-m-d') }}</td>
                                    <td class="py-2 font-bold text-slate-900 dark:text-white">{{ number_format((float) $order->total, 2) }} ₪</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="py-6 text-center text-sm text-slate-500 dark:text-slate-400">{{ __('لا توجد طلبات في هذه الفترة') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof Chart === 'undefined') return;
        const ctx = document.getElementById('salesChart');
        if (!ctx) return;

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: @json($summary['chart']['labels']),
                datasets: [
                    { label: 'المبيعات', data: @json($summary['chart']['sales']), backgroundColor: '#6366f1', borderRadius: 6 },
                    { label: 'التحصيل', data: @json($summary['chart']['payments']), backgroundColor: '#10b981', borderRadius: 6 },
                    { label: 'المصروفات', data: @json($summary['chart']['expenses']), backgroundColor: '#f59e0b', borderRadius: 6 }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        rtl: true,
                        callbacks: { label: (c) => c.dataset.label + ': ' + Number(c.parsed.y).toLocaleString('ar-EG') + ' ₪' }
                    }
                },
                scales: {
                    x: { grid: { display: false }, ticks: { font: { family: 'Cairo' } } },
                    y: { beginAtZero: true }
                }
            }
        });
    });
</script>