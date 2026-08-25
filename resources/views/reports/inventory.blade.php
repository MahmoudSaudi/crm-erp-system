<x-app-layout>
    <x-slot name="title">{{ __('تقرير المخزون') }}</x-slot>

    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-4">
                <a href="{{ route('reports.index') }}" class="flex items-center justify-center p-2 rounded-lg text-slate-500 bg-white border border-slate-200 hover:bg-slate-50 hover:text-slate-900 transition-colors dark:bg-slate-800 dark:border-slate-700 dark:text-slate-400 dark:hover:bg-slate-700 dark:hover:text-white" title="{{ __('العودة للتقارير') }}">
                    <svg class="w-5 h-5 rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </a>
                <div>
                    <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('تقرير المخزون') }}</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('قيمة المخزون والمنتجات منخفضة التوزيع حسب المستودعات') }}</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('عدد المنتجات') }}</p>
                <p class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">{{ number_format($summary['productCount']) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('قيمة المخزون') }}</p>
                <p class="mt-1 text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ number_format($summary['stockValue'], 2) }} ₪</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('منتجات منخفضة') }}</p>
                <p class="mt-1 text-2xl font-bold text-rose-600 dark:text-rose-400">{{ number_format($summary['lowStockCount']) }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">{{ __('التوزيع حسب المستودعات') }}</h3>
                <div class="mt-4 space-y-3">
                    @forelse ($summary['byWarehouse'] as $warehouse)
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ $warehouse['name'] }} <span class="text-xs text-slate-400">({{ $warehouse['code'] }})</span></span>
                            <span class="text-sm font-bold text-slate-900 dark:text-white">{{ number_format($warehouse['totalQty'], 2) }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('لا توجد مستودعات') }}</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">{{ __('منتجات منخفضة المخزون') }}</h3>
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-right text-xs text-slate-500 dark:text-slate-400">
                                <th class="pb-2 font-medium">{{ __('المنتج') }}</th>
                                <th class="pb-2 font-medium">{{ __('الكمية') }}</th>
                                <th class="pb-2 font-medium">{{ __('الحد الأدنى') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @forelse ($summary['lowStockItems'] as $product)
                                <tr>
                                    <td class="py-2 font-medium text-slate-900 dark:text-white">{{ $product->name }}</td>
                                    <td class="py-2 text-rose-600 font-bold dark:text-rose-400">{{ number_format($product->totalStockQuantity(), 2) }}</td>
                                    <td class="py-2 text-slate-600 dark:text-slate-300">{{ number_format((int) $product->min_stock) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="py-6 text-center text-sm text-slate-500 dark:text-slate-400">{{ __('لا توجد منتجات منخفضة') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white">{{ __('آخر حركات المخزون') }}</h3>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-right text-xs text-slate-500 dark:text-slate-400">
                            <th class="pb-2 font-medium">{{ __('التاريخ') }}</th>
                            <th class="pb-2 font-medium">{{ __('المنتج') }}</th>
                            <th class="pb-2 font-medium">{{ __('المستودع') }}</th>
                            <th class="pb-2 font-medium">{{ __('النوع') }}</th>
                            <th class="pb-2 font-medium">{{ __('الكمية') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($summary['latestMovements'] as $movement)
                            <tr>
                                <td class="py-2 text-slate-600 dark:text-slate-300">{{ $movement->created_at->format('Y-m-d H:i') }}</td>
                                <td class="py-2 font-medium text-slate-900 dark:text-white">{{ $movement->product?->name ?? '—' }}</td>
                                <td class="py-2 text-slate-600 dark:text-slate-300">{{ $movement->warehouse?->name ?? '—' }}</td>
                                <td class="py-2"><span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $movement->direction === 'in' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">{{ $movement->direction === 'in' ? 'وارد' : 'صادر' }}</span></td>
                                <td class="py-2 font-bold text-slate-900 dark:text-white">{{ number_format((float) $movement->quantity, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-6 text-center text-sm text-slate-500 dark:text-slate-400">{{ __('لا توجد حركات') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>