<x-app-layout>
    <x-slot name="title">{{ __('سجل التدقيق') }}</x-slot>

    <div class="space-y-6">
        <div>
            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('سجل التدقيق') }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">سجل بكل العمليات المنفذة داخل النظام</p>
        </div>

        {{-- Filters --}}
        <form method="GET" action="{{ route('audit-logs.index') }}" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                <div>
                    <x-input-label for="search" value="بحث" />
                    <x-text-input id="search" name="search" type="text" class="mt-1 block w-full" value="{{ request('search') }}" placeholder="الإجراء، النموذج، IP..." />
                </div>
                <div>
                    <x-input-label for="action" value="الإجراء" />
                    <select id="action" name="action" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                        <option value="">كل الإجراءات</option>
                        @foreach ($actions as $action)
                            <option value="{{ $action }}" @selected($activeAction === $action)>{{ $action }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="user_id" value="المستخدم" />
                    <select id="user_id" name="user_id" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                        <option value="">كل المستخدمين</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected($activeUser == $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600">
                        {{ __('تصفية') }}
                    </button>
                    @if (request()->hasAny(['search', 'action', 'user_id']))
                        <a href="{{ route('audit-logs.index') }}" class="ms-2 inline-flex items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-800">{{ __('مسح') }}</a>
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
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">الوقت</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">المستخدم</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">الإجراء</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">النموذج</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">IP</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($logs as $log)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                                <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">{{ $log->user?->name ?: 'نظام' }}</td>
                                <td class="px-4 py-3"><span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300" dir="ltr">{{ $log->action }}</span></td>
                                <td class="px-4 py-3 text-xs text-slate-500 dark:text-slate-400" dir="ltr">{{ class_basename($log->model_type) }}</td>
                                <td class="px-4 py-3 text-sm text-slate-500 dark:text-slate-400" dir="ltr">{{ $log->ip ?: '—' }}</td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('audit-logs.show', $log) }}" class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium text-indigo-600 hover:bg-indigo-50 dark:text-indigo-400 dark:hover:bg-indigo-500/10">
                                        {{ __('عرض') }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <x-empty-state icon="document" title="{{ __('لا توجد سجلات بعد') }}" :description="__('لم تُسجَّل أي عمليات بعد. ستظهر هنا سجلات نشاط المستخدمين على النظام.')" />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($logs->hasPages())
                <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-700">{{ $logs->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>