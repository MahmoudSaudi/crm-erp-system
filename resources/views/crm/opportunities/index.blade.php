<x-app-layout>
    <x-slot name="title">{{ __('الفرص') }}</x-slot>

    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('فرص المبيعات') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">تتبع الفرص عبر مراحل البيع حتى الإغلاق</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('opportunities.pipeline') }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" /></svg>
                    {{ __('عرض الكانبان') }}
                </a>
                @can('permission.create_opportunities')
                    <a href="{{ route('opportunities.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-violet-700">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                        {{ __('إضافة فرصة') }}
                    </a>
                @endcan
            </div>
        </div>

        <form method="GET" action="{{ route('opportunities.index') }}" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <x-input-label for="search" value="بحث" />
                    <x-text-input id="search" name="search" type="text" class="mt-1 block w-full" value="{{ request('search') }}" placeholder="عنوان الفرصة..." />
                </div>
                <div>
                    <x-input-label for="stage_id" value="المرحلة" />
                    <select id="stage_id" name="stage_id" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                        <option value="">كل المراحل</option>
                        @foreach ($stages as $stage)
                            <option value="{{ $stage->id }}" @selected((string) $activeStage === (string) $stage->id)>{{ $stage->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600">{{ __('تصفية') }}</button>
                    @if (request()->hasAny(['search', 'stage_id']))
                        <a href="{{ route('opportunities.index') }}" class="ms-2 inline-flex items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-800">{{ __('مسح') }}</a>
                    @endif
                </div>
            </div>
        </form>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-800/60">
                        <tr>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">الفرصة</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">العميل</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">المرحلة</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">المبلغ</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">الاحتمالية</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">مندوب المبيعات</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">إغلاق متوقع</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($opportunities as $opportunity)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="px-4 py-3">
                                    <a href="{{ route('opportunities.show', $opportunity) }}" class="font-medium text-slate-900 hover:text-violet-600 dark:text-white dark:hover:text-violet-400">{{ $opportunity->title }}</a>
                                </td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('customers.show', $opportunity->customer_id) }}" class="text-sm text-slate-600 hover:text-indigo-600 dark:text-slate-300">{{ $opportunity->customer?->name }}</a>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium text-slate-700 dark:text-slate-300">
                                        <span class="h-2 w-2 rounded-full" style="background: {{ $opportunity->stage?->color }}"></span>
                                        {{ $opportunity->stage?->name }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm font-medium text-slate-700 dark:text-slate-200">{{ number_format((float) $opportunity->amount, 0) }} ₪</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <div class="h-1.5 w-16 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                                            <div class="h-full rounded-full bg-violet-500" style="width: {{ $opportunity->probability }}%"></div>
                                        </div>
                                        <span class="text-xs text-slate-500 dark:text-slate-400">{{ $opportunity->probability }}%</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">{{ $opportunity->assignedTo?->name ?: '—' }}</td>
                                <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">{{ $opportunity->expected_close_date?->format('Y-m-d') ?: '—' }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-1">
                                        <a href="{{ route('opportunities.show', $opportunity) }}" class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-300" title="عرض">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                        </a>
                                        @can('permission.edit_opportunities')
                                            <a href="{{ route('opportunities.edit', $opportunity) }}" class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-300" title="تعديل">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                            </a>
                                        @endcan
                                        @can('permission.delete_opportunities')
                                            <form method="POST" action="{{ route('opportunities.destroy', $opportunity) }}" onsubmit="return confirm('حذف هذه الفرصة؟')">
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
                                <td colspan="8">
                                    <x-empty-state icon="chart" title="{{ __('لا توجد فرص بعد') }}" :description="__('لم تُسجَّل أي فرص بيع بعد. أضِف فرصة لمتابعة صفقاتك وعائداتها المتوقعة.')" />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($opportunities->hasPages())
                <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-700">{{ $opportunities->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>