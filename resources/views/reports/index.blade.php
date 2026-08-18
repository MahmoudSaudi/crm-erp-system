<x-app-layout>
    <x-slot name="title">{{ __('التقارير') }}</x-slot>

    <div class="space-y-6">
        <div>
            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('التقارير') }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('نظرة تحليلية على المبيعات والمخزون والأرباح والمصروفات والمستحقات المتأخرة') }}</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <a href="{{ route('reports.sales') }}" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-indigo-300 hover:shadow-md dark:border-slate-700 dark:bg-slate-900 dark:hover:border-indigo-500">
                <div class="flex items-center justify-center w-12 h-12 rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-300">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" /></svg>
                </div>
                <div class="mt-4 text-lg font-bold text-slate-900 dark:text-white">{{ __('تقرير المبيعات') }}</div>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('الإيرادات، التحصيل، المستحق، وأعلى العملاء') }}</p>
            </a>

            <a href="{{ route('reports.inventory') }}" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-emerald-300 hover:shadow-md dark:border-slate-700 dark:bg-slate-900 dark:hover:border-emerald-500">
                <div class="flex items-center justify-center w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-300">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11a4 4 0 11 0 8m0-8a4 4 0 11 0-8m0 8h8m-8 0a4 4 0 000-8m-4 8a4 4 0 11-8 0" /></svg>
                </div>
                <div class="mt-4 text-lg font-bold text-slate-900 dark:text-white">{{ __('تقرير المخزون') }}</div>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('قيمة المخزون، المنتجات منخفضة، والتوزيع بالمستودعات') }}</p>
            </a>

            <a href="{{ route('reports.profit') }}" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-amber-300 hover:shadow-md dark:border-slate-700 dark:bg-slate-900 dark:hover:border-amber-500">
                <div class="flex items-center justify-center w-12 h-12 rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-500/20 dark:text-amber-300">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                </div>
                <div class="mt-4 text-lg font-bold text-slate-900 dark:text-white">{{ __('تقرير الأرباح') }}</div>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('الإيرادات مقابل التكلفة والمصروفات وصافي الربح') }}</p>
            </a>

            <a href="{{ route('reports.expenses') }}" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-rose-300 hover:shadow-md dark:border-slate-700 dark:bg-slate-900 dark:hover:border-rose-500">
                <div class="flex items-center justify-center w-12 h-12 rounded-xl bg-rose-50 text-rose-600 dark:bg-rose-500/20 dark:text-rose-300">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0l3-3m-3 3l-3-3m6 9H6a2 2 0 01-2-2V7a2 2 0 012-2h12a2 2 0 012 2v9a2 2 0 01-2 2z" /></svg>
                </div>
                <div class="mt-4 text-lg font-bold text-slate-900 dark:text-white">{{ __('تقرير المصروفات') }}</div>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('إجمالي المصروفات وتحليلها حسب التصنيف والشهر') }}</p>
            </a>

            <a href="{{ route('reports.overdue') }}" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-sky-300 hover:shadow-md dark:border-slate-700 dark:bg-slate-900 dark:hover:border-sky-500">
                <div class="flex items-center justify-center w-12 h-12 rounded-xl bg-sky-50 text-sky-600 dark:bg-sky-500/20 dark:text-sky-300">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </div>
                <div class="mt-4 text-lg font-bold text-slate-900 dark:text-white">{{ __('الفواتير المتأخرة') }}</div>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('الفواتير غير المدفوعة بعد تاريخ الاستحقاق') }}</p>
            </a>
        </div>
    </div>
</x-app-layout>