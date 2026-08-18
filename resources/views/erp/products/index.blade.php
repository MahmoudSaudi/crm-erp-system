<x-app-layout>
    <x-slot name="title">{{ __('المنتجات') }}</x-slot>

    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('المنتجات') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">منتجاتك، أسعارها، ومخزونها</p>
            </div>
            @can('permission.create_products')
                <a href="{{ route('products.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                    {{ __('إضافة منتج') }}
                </a>
            @endcan
        </div>

        {{-- Filters --}}
        <form method="GET" action="{{ route('products.index') }}" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <x-input-label for="search" value="بحث" />
                    <x-text-input id="search" name="search" type="text" class="mt-1 block w-full" value="{{ request('search') }}" placeholder="الاسم، SKU، الباركود..." />
                </div>
                <div>
                    <x-input-label for="category_id" value="التصنيف" />
                    <select id="category_id" name="category_id" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                        <option value="">كل التصنيفات</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected($activeCategory == $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="stock_status" value="حالة المخزون" />
                    <select id="stock_status" name="stock_status" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                        <option value="">الكل</option>
                        <option value="low" @selected($activeStatus === 'low')>منخفض المخزون</option>
                        <option value="out" @selected($activeStatus === 'out')>نفد المخزون</option>
                    </select>
                </div>
                <div class="sm:col-span-3 flex items-end gap-2">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                        {{ __('تصفية') }}
                    </button>
                    @if (request()->hasAny(['search', 'category_id', 'stock_status']))
                        <a href="{{ route('products.index') }}" class="ms-2 inline-flex items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-800">{{ __('مسح') }}</a>
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
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">التصنيف</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">الوحدة</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">سعر البيع</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">المخزون</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">الحالة</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($products as $product)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-slate-900 dark:text-white">{{ $product->name }}</div>
                                    <div class="text-xs text-slate-500 dark:text-slate-400" dir="ltr">{{ $product->sku }}</div>
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">{{ $product->category?->name ?: '—' }}</td>
                                <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">{{ $product->unit?->name ?: '—' }}</td>
                                <td class="px-4 py-3 text-sm font-medium text-slate-700 dark:text-slate-200">{{ number_format((float) $product->sale_price, 2) }} ₪</td>
                                <td class="px-4 py-3 text-sm font-medium text-slate-700 dark:text-slate-200">{{ number_format((float) $product->stock_quantity, 2) }}</td>
                                <td class="px-4 py-3">
                                    @if (! $product->is_active)
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400">معطل</span>
                                    @elseif ($product->stock_quantity <= 0)
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400">نفد</span>
                                    @elseif ($product->isLowStock())
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400">منخفض</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400">متوفر</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-1">
                                        @can('permission.edit_products')
                                            <a href="{{ route('products.edit', $product) }}" class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-300" title="تعديل">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                            </a>
                                        @endcan
                                        @can('permission.delete_products')
                                            <form method="POST" action="{{ route('products.destroy', $product) }}" onsubmit="return confirm('حذف هذا المنتج؟')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="rounded-md p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10 dark:hover:text-rose-400" title="حذف">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <x-empty-state icon="inbox" title="{{ __('لا توجد منتجات بعد') }}" :description="__('أضف منتجاتك الأولى مع الوحدات والأسعار.')" />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($products->hasPages())
                <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-700">{{ $products->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>