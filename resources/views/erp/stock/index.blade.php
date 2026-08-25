<x-app-layout>
    <x-slot name="title">{{ __('المخزون') }}</x-slot>

    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('المخزون') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">رصيد المنتجات في كل مستودع</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('stock.movements') }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16" /></svg>
                    {{ __('سجل الحركات') }}
                </a>
                @can('permission.adjust_stock')
                    <a href="#adjust" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                        {{ __('جرد / تصحيح') }}
                    </a>
                @endcan
            </div>
        </div>

        @if ($lowCount > 0)
            <div class="flex items-center gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-700 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-400">
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                {{ $lowCount }} صنف وصل إلى حد الطلب الأدنى أو أقل.
                <a href="{{ route('stock.index', ['scope' => 'low']) }}" class="ms-1 font-semibold underline">عرض الأصناف المنخفضة</a>
            </div>
        @endif

        {{-- Filters --}}
        <form method="GET" action="{{ route('stock.index') }}" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                <div>
                    <x-input-label for="search" value="بحث" />
                    <x-text-input id="search" name="search" type="text" class="mt-1 block w-full" value="{{ request('search') }}" placeholder="اسم المنتج أو SKU..." />
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
                    <x-input-label for="scope" value="النطاق" />
                    <select id="scope" name="scope" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                        <option value="all" @selected($activeScope === 'all')>كل الأصناف</option>
                        <option value="low" @selected($activeScope === 'low')>منخفض المخزون</option>
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                        {{ __('تصفية') }}
                    </button>
                    @if (request()->hasAny(['search', 'warehouse_id', 'scope']))
                        <a href="{{ route('stock.index') }}" class="ms-2 inline-flex items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-800">{{ __('مسح') }}</a>
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
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">المنتج</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">المستودع</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">الرصيد الحالي</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">الحد الأدنى</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">الحالة</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($items as $item)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-slate-900 dark:text-white">{{ $item->product?->name }}</div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400" dir="ltr">{{ $item->product?->sku }}</div>
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">{{ $item->warehouse?->name }}</td>
                                <td class="px-4 py-3 text-sm font-semibold text-slate-900 dark:text-white">{{ number_format((float) $item->quantity, 2) }}</td>
                                <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">{{ $item->product?->min_stock }}</td>
                                <td class="px-4 py-3">
                                    @if ((float) $item->quantity <= 0)
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400">نفد</span>
                                    @elseif ((float) $item->quantity <= $item->product?->min_stock)
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400">منخفض</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400">متوفر</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <x-empty-state icon="chart" title="{{ __('لا يوجد مخزون مسجل') }}" :description="__('لم يُسجَّل مخزون بعد. أضِف منتجات أو أدخل كميات أولية لتفعيل إدارة المخزون.')" />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($items->hasPages())
                <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-700">{{ $items->links() }}</div>
            @endif
        </div>

        {{-- Adjustment form --}}
        @can('permission.adjust_stock')
            <div id="adjust" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">جرد / تصحيح المخزون</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">اضبط الرصيد الفعلي لمنتج في مستودع بعد الجرد الفيزيائي</p>

                <form method="POST" action="{{ route('stock.adjust') }}" class="mt-4 grid grid-cols-1 sm:grid-cols-4 gap-3">
                    @csrf
                    <div>
                        <select name="product_id" required class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                            <option value="">اختر المنتج</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}" @selected(request('product_id') == $product->id)>{{ $product->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <select name="warehouse_id" required class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                            <option value="">اختر المستودع</option>
                            @foreach ($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-text-input name="new_quantity" type="number" step="0.01" min="0" class="mt-1 block w-full" placeholder="الكمية الفعلية" required />
                    </div>
                    <div>
                        <button type="submit" class="inline-flex w-full items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">تسوية</button>
                    </div>
                    <div class="sm:col-span-4">
                        <x-text-input name="note" type="text" class="mt-1 block w-full" placeholder="ملاحظة الجرد (اختياري)" />
                    </div>
                </form>
            </div>
        @endcan
    </div>
</x-app-layout>