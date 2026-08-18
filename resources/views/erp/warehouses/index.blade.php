<x-app-layout>
    <x-slot name="title">{{ __('المستودعات') }}</x-slot>

    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('المستودعات') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">إدارة مواقع التخزين وتوزيع المخزون داخلها</p>
            </div>
            @can('permission.manage_warehouses')
                <a href="{{ route('warehouses.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                    {{ __('إضافة مستودع') }}
                </a>
            @endcan
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @forelse ($warehouses as $warehouse)
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <div class="flex items-start justify-between">
                        <div>
                            <h3 class="font-semibold text-slate-900 dark:text-white">{{ $warehouse->name }}</h3>
                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400" dir="ltr">{{ $warehouse->code }}</div>
                            @if ($warehouse->address)
                                <div class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ $warehouse->address }}</div>
                            @endif
                        </div>
                        @if ($warehouse->is_active)
                            <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400">نشط</span>
                        @else
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500 dark:bg-slate-800 dark:text-slate-400">معطل</span>
                        @endif
                    </div>
                    <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-3 text-sm dark:border-slate-800">
                        <span class="text-slate-500 dark:text-slate-400">إجمالي الرصيد</span>
                        <span class="font-semibold text-slate-900 dark:text-white">{{ number_format((float) $warehouse->stock_items_sum_quantity, 2) }}</span>
                    </div>
                    <div class="mt-2 flex items-center justify-between text-sm">
                        <span class="text-slate-500 dark:text-slate-400">أصناف</span>
                        <span class="font-semibold text-slate-900 dark:text-white">{{ $warehouse->stock_items_count }}</span>
                    </div>
                    <div class="mt-4 flex items-center gap-2">
                        @can('permission.manage_warehouses')
                            <a href="{{ route('warehouses.edit', $warehouse) }}" class="inline-flex flex-1 items-center justify-center gap-1 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                تعديل
                            </a>
                            <form method="POST" action="{{ route('warehouses.destroy', $warehouse) }}" onsubmit="return confirm('حذف هذا المستودع؟')">
                                @csrf @method('DELETE')
                                <button type="submit" class="inline-flex items-center justify-center rounded-lg border border-transparent px-3 py-1.5 text-xs font-medium text-slate-400 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10 dark:hover:text-rose-400">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                </button>
                            </form>
                        @endcan
                    </div>
                </div>
            @empty
                <div class="sm:col-span-2 lg:col-span-3 rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <x-empty-state icon="inbox" title="{{ __('لا توجد مستودعات بعد') }}" :description="__('أضِف أول مستودع لتتبع مواقع تخزين المنتجات وحصر المخزون بدقة.')" />
                </div>
            @endforelse
        </div>

        @if ($warehouses->hasPages())
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm dark:border-slate-700 dark:bg-slate-900">{{ $warehouses->links() }}</div>
        @endif
    </div>
</x-app-layout>