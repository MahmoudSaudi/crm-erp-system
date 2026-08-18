<x-app-layout>
    <x-slot name="title">{{ __('تفاصيل السجل') }}</x-slot>

    <div class="mx-auto max-w-3xl space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('تفاصيل السجل') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">تفاصيل العملية المسجلة في سجل التدقيق</p>
            </div>
            <a href="{{ route('audit-logs.index') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">{{ __('رجوع') }}</a>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <dt class="text-xs font-semibold text-slate-500 dark:text-slate-400">الإجراء</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900 dark:text-white" dir="ltr">{{ $log->action }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold text-slate-500 dark:text-slate-400">المستخدم</dt>
                    <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $log->user?->name ?: 'نظام' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold text-slate-500 dark:text-slate-400">النموذج</dt>
                    <dd class="mt-1 text-xs text-slate-700 dark:text-slate-200" dir="ltr">{{ $log->model_type }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold text-slate-500 dark:text-slate-400">معرّف السجل</dt>
                    <dd class="mt-1 text-sm text-slate-900 dark:text-white">#{{ $log->model_id }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold text-slate-500 dark:text-slate-400">الوقت</dt>
                    <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $log->created_at->format('d/m/Y H:i:s') }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold text-slate-500 dark:text-slate-400">IP</dt>
                    <dd class="mt-1 text-sm text-slate-900 dark:text-white" dir="ltr">{{ $log->ip ?: '—' }}</dd>
                </div>
            </dl>
        </div>

        @if ($log->old_values || $log->new_values)
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                @if ($log->old_values)
                    <h3 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-200">القيم القديمة</h3>
                    <div class="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
                        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @foreach ($log->old_values as $key => $value)
                                    <tr>
                                        <td class="px-4 py-2 text-xs font-medium text-slate-500 dark:text-slate-400">{{ $key }}</td>
                                        <td class="px-4 py-2 text-sm text-slate-700 dark:text-slate-200">{{ is_scalar($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if ($log->new_values)
                    <h3 class="mb-3 mt-6 text-sm font-semibold text-slate-700 dark:text-slate-200">القيم الجديدة</h3>
                    <div class="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
                        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @foreach ($log->new_values as $key => $value)
                                    <tr>
                                        <td class="px-4 py-2 text-xs font-medium text-slate-500 dark:text-slate-400">{{ $key }}</td>
                                        <td class="px-4 py-2 text-sm text-slate-700 dark:text-slate-200">{{ is_scalar($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-app-layout>