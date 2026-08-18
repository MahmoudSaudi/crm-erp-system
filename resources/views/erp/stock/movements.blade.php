<x-app-layout>
    <x-slot name="title">{{ __('سجل حركات المخزون') }}</x-slot>

    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('سجل حركات المخزون') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">كل حركات الدخول والخروج والتسويات</p>
            </div>
            <a href="{{ route('stock.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700">
                {{ __('عودة للمخزون') }}
            </a>
        </div>

        {{-- Filters --}}
        <form method="GET" action="{{ route('stock.movements') }}" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                <div>
                    <x-input-label for="product_id" value="المنتج" />
                    <select id="product_id" name="product_id" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                        <option value="">كل المنتجات</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}" @selected($activeProduct == $product->id)>{{ $product->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="warehouse_id" value="المستودع" />
                    <select id="warehouse_id" name="warehouse_id" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                        <option value="">كل المستودعات</option>
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" @selected($activeWarehouse == $warehouse->id)>{{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="direction" value="الاتجاه" />
                    <select id="direction" name="direction" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                        <option value="">الكل</option>
                        <option value="in" @selected($activeDirection === 'in')>دخول</option>
                        <option value="out" @selected($activeDirection === 'out')>خروج</option>
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                        {{ __('تصفية') }}
                    </button>
                    @if (request()->hasAny(['product_id', 'warehouse_id', 'direction']))
                        <a href="{{ route('stock.movements') }}" class="ms-2 inline-flex items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-800">{{ __('مسح') }}</a>
                    @endif
                </div>
            </div>
        </form>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-800/60">
                        <tr>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">التاريخ</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">المنتج</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">المستودع</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">الاتجاه</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">الكمية</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">قبل / بعد</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">الملاحظة</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($movements as $movement)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">{{ $movement->created_at->format('Y-m-d H:i') }}</td>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-slate-900 dark:text-white">{{ $movement->product?->name }}</div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400" dir="ltr">{{ $movement->product?->sku }}</div>
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">{{ $movement->warehouse?->name }}</td>
                                <td class="px-4 py-3">
                                    @if ($movement->direction === 'in')
                                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400">دخول</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-rose-50 px-2.5 py-0.5 text-xs font-medium text-rose-600 dark:bg-rose-500/10 dark:text-rose-400">خروج</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm font-semibold text-slate-900 dark:text-white">{{ number_format((float) $movement->quantity, 2) }}</td>
                                <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">{{ number_format((float) $movement->before_qty, 2) }} ← {{ number_format((float) $movement->after_qty, 2) }}</td>
                                <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">{{ $movement->note ?: $movement->type }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <x-empty-state icon="chart" title="{{ __('لا توجد حركات مخزون بعد') }}" :description="__('لم تُسجَّل أي حركات دخول أو خروج بعد. تظهر هنا كل تغييرات كميات المخزون.')" />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($movements->hasPages())
                <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-700">{{ $movements->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>