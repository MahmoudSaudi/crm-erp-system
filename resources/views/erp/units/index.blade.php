<x-app-layout>
    <x-slot name="title">{{ __('وحدات القياس') }}</x-slot>

    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('وحدات القياس') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">وحدات مثل قطعة، كرتونة، متر، كيلو...</p>
            </div>
            @can('permission.manage_units')
                <a href="{{ route('units.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                    {{ __('إضافة وحدة') }}
                </a>
            @endcan
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-800/60">
                        <tr>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">الاسم</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">الرمز</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">عدد المنتجات</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($units as $unit)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="px-4 py-3 font-medium text-slate-900 dark:text-white">{{ $unit->name }}</td>
                                <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">{{ $unit->code }}</td>
                                <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">{{ $unit->products_count }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-1">
                                        @can('permission.manage_units')
                                            <a href="{{ route('units.edit', $unit) }}" class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-300" title="تعديل">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                            </a>
                                            <form method="POST" action="{{ route('units.destroy', $unit) }}" onsubmit="return confirm('حذف هذه الوحدة؟')">
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
                                <td colspan="4">
                                    <x-empty-state icon="inbox" title="{{ __('لا توجد وحدات قياس بعد') }}" :description="__('أضِف وحدات مثل قطعة، كرتونة، متر أو كيلو لاستخدامها في تسعير المنتجات وإدارتها.')" />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($units->hasPages())
                <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-700">{{ $units->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
