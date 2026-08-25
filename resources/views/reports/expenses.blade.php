<x-app-layout>
    <x-slot name="title">{{ __('تقرير المصروفات') }}</x-slot>

    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-4">
                <a href="{{ route('reports.index') }}" class="flex items-center justify-center p-2 rounded-lg text-slate-500 bg-white border border-slate-200 hover:bg-slate-50 hover:text-slate-900 transition-colors dark:bg-slate-800 dark:border-slate-700 dark:text-slate-400 dark:hover:bg-slate-700 dark:hover:text-white" title="{{ __('العودة للتقارير') }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </a>
                <div>
                    <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('تقرير المصروفات') }}</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('إجمالي المصروفات وتحليلها حسب التصنيف والشهر') }}</p>
                </div>
            </div>
            <form method="GET" action="{{ route('reports.expenses') }}" class="flex items-center gap-2">
                <input type="month" name="month" value="{{ $month }}" class="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900">
                <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">{{ __('تصفية') }}</button>
                @if ($month)
                    <a href="{{ route('reports.expenses') }}" class="text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400">{{ __('إزالة التصفية') }}</a>
                @endif
            </form>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('إجمالي المصروفات') }}</p>
            <p class="mt-1 text-2xl font-bold text-rose-600 dark:text-rose-400">{{ number_format($summary['total'], 2) }} ₪</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">{{ __('حسب التصنيف') }}</h3>
                <div class="mt-4 space-y-3">
                    @forelse ($summary['byCategory'] as $row)
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ $row['category'] }} <span class="text-xs text-slate-400">({{ $row['count'] }})</span></span>
                            <span class="text-sm font-bold text-rose-600 dark:text-rose-400">{{ number_format($row['total'], 2) }} ₪</span>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('لا توجد مصروفات') }}</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">{{ __('حسب الشهر') }}</h3>
                <div class="mt-4 space-y-3">
                    @forelse ($summary['byMonth'] as $row)
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ $row['month'] }}</span>
                            <span class="text-sm font-bold text-slate-900 dark:text-white">{{ number_format($row['total'], 2) }} ₪</span>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('لا توجد مصروفات') }}</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-app-layout>