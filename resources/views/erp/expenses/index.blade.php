<x-app-layout>
    <x-slot name="title">{{ __('المصروفات') }}</x-slot>

    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('المصروفات') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">تتبع مصروفات الشركة التشغيلية</p>
            </div>
            @can('permission.create_expenses')
                <a href="{{ route('expenses.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                    {{ __('تسجيل مصروف') }}
                </a>
            @endcan
        </div>

        @if ($expenses->count())
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <p class="text-sm text-slate-500 dark:text-slate-400">إجمالي المصروفات المعروضة</p>
                <p class="mt-1 text-2xl font-bold text-rose-600 dark:text-rose-400">{{ number_format((float) $total, 2) }} ₪</p>
            </div>
        @endif

        {{-- Filters --}}
        <form method="GET" action="{{ route('expenses.index') }}" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                <div>
                    <x-input-label for="search" value="بحث" />
                    <x-text-input id="search" name="search" type="text" class="mt-1 block w-full" value="{{ request('search') }}" placeholder="الوصف أو التصنيف..." />
                </div>
                <div>
                    <x-input-label for="category" value="التصنيف" />
                    <select id="category" name="category" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                        <option value="">كل التصنيفات</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category }}" @selected($activeCategory === $category)>{{ $category }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="month" value="الشهر" />
                    <x-text-input id="month" name="month" type="month" class="mt-1 block w-full" value="{{ $activeMonth ?? now()->format('Y-m') }}" />
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                        {{ __('تصفية') }}
                    </button>
                    @if (request()->hasAny(['search', 'category', 'month']))
                        <a href="{{ route('expenses.index') }}" class="ms-2 inline-flex items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-800">{{ __('مسح') }}</a>
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
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">التصنيف</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">الوصف</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">المبلغ</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">التاريخ</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">قابل للاسترداد</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">سُجل بواسطة</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($expenses as $expense)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $expense->category }}</span>
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-700 dark:text-slate-200">{{ $expense->description ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm font-semibold text-rose-600 dark:text-rose-400">{{ number_format((float) $expense->amount, 2) }} ₪</td>
                                <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">{{ $expense->date?->format('Y-m-d') }}</td>
                                <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">
                                    @if ($expense->is_reimbursable)
                                        <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-500/10 dark:text-amber-400">نعم</span>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">{{ $expense->creator?->name ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-1">
                                        @can('permission.edit_expenses')
                                            <a href="{{ route('expenses.edit', $expense) }}" class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-300" title="تعديل">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                            </a>
                                        @endcan
                                        @can('permission.delete_expenses')
                                            <form method="POST" action="{{ route('expenses.destroy', $expense) }}" onsubmit="return confirm('حذف المصروف؟')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="rounded-md p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10" title="حذف">
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
                                    <x-empty-state icon="chart" title="{{ __('لا توجد مصروفات مسجلة') }}" :description="__('لم تُسجَّل أي مصروفات بعد. سجّل أول مصروف لتتبع تكاليف التشغيل.')" />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($expenses->hasPages())
                <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-700">{{ $expenses->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>