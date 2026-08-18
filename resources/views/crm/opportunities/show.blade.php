<x-app-layout>
    <x-slot name="title">{{ $opportunity->title }}</x-slot>

    <div class="space-y-6">
        {{-- Header --}}
        <div class="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ $opportunity->title }}</h2>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium text-slate-700 dark:text-slate-300">
                        <span class="h-2 w-2 rounded-full" style="background: {{ $opportunity->stage?->color }}"></span>
                        {{ $opportunity->stage?->name }}
                    </span>
                    <a href="{{ route('customers.show', $opportunity->customer_id) }}" class="text-sm text-slate-500 hover:text-indigo-600 dark:text-slate-400">← {{ $opportunity->customer?->name }}</a>
                </div>
            </div>
            <div class="flex gap-2">
                @can('permission.edit_opportunities')
                    <a href="{{ route('opportunities.edit', $opportunity) }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">{{ __('تعديل') }}</a>
                @endcan
            </div>
        </div>

        {{-- Metrics --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div class="text-xs font-medium text-slate-500 dark:text-slate-400">المبلغ المتوقع</div>
                <div class="mt-1 text-lg font-bold text-slate-900 dark:text-white">{{ number_format((float) $opportunity->amount, 2) }} ₪</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div class="text-xs font-medium text-slate-500 dark:text-slate-400">نسبة الاحتمالية</div>
                <div class="mt-1 text-lg font-bold text-violet-600 dark:text-violet-400">{{ $opportunity->probability }}%</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div class="text-xs font-medium text-slate-500 dark:text-slate-400">الإغلاق المتوقع</div>
                <div class="mt-1 text-lg font-bold text-slate-900 dark:text-white">{{ $opportunity->expected_close_date?->format('Y-m-d') ?: '—' }}</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div class="text-xs font-medium text-slate-500 dark:text-slate-400">مندوب المبيعات</div>
                <div class="mt-1 text-lg font-bold text-slate-900 dark:text-white">{{ $opportunity->assignedTo?->name ?: '—' }}</div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            {{-- Description --}}
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900 lg:col-span-2">
                <h3 class="mb-3 font-semibold text-slate-900 dark:text-white">وصف الفرصة</h3>
                <p class="text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $opportunity->description ?: 'لا يوجد وصف' }}</p>
            </div>

            {{-- Referral info --}}
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <h3 class="mb-3 font-semibold text-slate-900 dark:text-white">معلومات إضافية</h3>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500 dark:text-slate-400">منشئ الفرصة</dt><dd class="font-medium text-slate-800 dark:text-slate-200">{{ $opportunity->creator?->name }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500 dark:text-slate-400">تاريخ الإنشاء</dt><dd class="font-medium text-slate-800 dark:text-slate-200">{{ $opportunity->created_at->format('Y-m-d') }}</dd></div>
                    @if ($opportunity->closed_at)
                        <div class="flex justify-between"><dt class="text-slate-500 dark:text-slate-400">تاريخ الإغلاق</dt><dd class="font-medium text-slate-800 dark:text-slate-200">{{ $opportunity->closed_at->format('Y-m-d H:i') }}</dd></div>
                    @endif
                </dl>
            </div>
        </div>
    </div>
</x-app-layout>