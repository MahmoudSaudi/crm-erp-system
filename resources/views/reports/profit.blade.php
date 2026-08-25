<x-app-layout>
    <x-slot name="title">{{ __('تقرير الأرباح') }}</x-slot>

    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-4">
                <a href="{{ route('reports.index') }}" class="flex items-center justify-center p-2 rounded-lg text-slate-500 bg-white border border-slate-200 hover:bg-slate-50 hover:text-slate-900 transition-colors dark:bg-slate-800 dark:border-slate-700 dark:text-slate-400 dark:hover:bg-slate-700 dark:hover:text-white" title="{{ __('العودة للتقارير') }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </a>
                <div>
                    <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('تقرير الأرباح') }}</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('الإيرادات مقابل تكلفة البضاعة والمصروفات') }}</p>
                </div>
            </div>
            <form method="GET" action="{{ route('reports.profit') }}" class="flex items-center gap-2">
                <input type="month" name="month" value="{{ $month }}" class="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900">
                <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">{{ __('تصفية') }}</button>
                @if ($month)
                    <a href="{{ route('reports.profit') }}" class="text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400">{{ __('إزالة التصفية') }}</a>
                @endif
            </form>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('الإيرادات') }}</p>
                <p class="mt-1 text-2xl font-bold text-indigo-600 dark:text-indigo-400">{{ number_format($summary['revenue'], 2) }} ₪</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('تكلفة البضاعة') }}</p>
                <p class="mt-1 text-2xl font-bold text-amber-600 dark:text-amber-400">{{ number_format($summary['cogs'], 2) }} ₪</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('مجمل الربح') }}</p>
                <p class="mt-1 text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ number_format($summary['grossProfit'], 2) }} ₪</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('صافي الربح') }}</p>
                <p class="mt-1 text-2xl font-bold {{ $summary['netProfit'] >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">{{ number_format($summary['netProfit'], 2) }} ₪</p>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white">{{ __('ملخص') }}</h3>
            <dl class="mt-4 space-y-3 text-sm">
                <div class="flex items-center justify-between">
                    <dt class="text-slate-500 dark:text-slate-400">{{ __('المصروفات') }}</dt>
                    <dd class="font-bold text-slate-900 dark:text-white">{{ number_format($summary['expenses'], 2) }} ₪</dd>
                </div>
                <div class="flex items-center justify-between border-t border-slate-100 pt-3 dark:border-slate-800">
                    <dt class="text-slate-500 dark:text-slate-400">{{ __('صافي الربح = الإيرادات − التكلفة − المصروفات') }}</dt>
                    <dd class="font-bold {{ $summary['netProfit'] >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">{{ number_format($summary['netProfit'], 2) }} ₪</dd>
                </div>
            </dl>
        </div>
    </div>
</x-app-layout>