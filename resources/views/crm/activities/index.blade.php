<x-app-layout>
    <x-slot name="title">{{ __('الأنشطة') }}</x-slot>

    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('الأنشطة') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">المكالمات والاجتماعات والمهام المرتبطة بالعملاء والفرص</p>
            </div>
            @can('permission.create_activities')
                <a href="{{ route('activities.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-sky-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-sky-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                    {{ __('إضافة نشاط') }}
                </a>
            @endcan
        </div>

        <form method="GET" action="{{ route('activities.index') }}" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <x-input-label for="type" value="النوع" />
                    <select id="type" name="type" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                        <option value="">كل الأنواع</option>
                        @foreach ($types as $value => $label)
                            <option value="{{ $value }}" @selected($activeType === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="scope" value="الحالة" />
                    <select id="scope" name="scope" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                        <option value="all" @selected($activeScope === 'all')>الكل</option>
                        <option value="pending" @selected($activeScope === 'pending')>قيد الانتظار</option>
                        <option value="completed" @selected($activeScope === 'completed')>مكتمل</option>
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600">{{ __('تصفية') }}</button>
                    @if (request()->hasAny(['type', 'scope']))
                        <a href="{{ route('activities.index') }}" class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-800">{{ __('مسح') }}</a>
                    @endif
                </div>
            </div>
        </form>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <ol class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($activities as $activity)
                    @php $activityType = \App\Enums\ActivityType::tryFrom($activity->type); @endphp
                    <li class="flex items-start gap-4 p-4">
                        <span class="mt-1 flex h-9 w-9 shrink-0 items-center justify-center rounded-full {{ $activity->isCompleted() ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-400' : 'bg-sky-100 text-sky-600 dark:bg-sky-500/20 dark:text-sky-400' }}">
                            @if ($activity->isCompleted())
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                            @else
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            @endif
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold text-slate-900 dark:text-white">{{ $activity->subject }}</span>
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600 dark:bg-slate-700 dark:text-slate-300">{{ $activityType?->label() ?: $activity->type }}</span>
                                @if ($activity->scheduled_at)
                                    <span class="text-xs text-amber-600 dark:text-amber-400">موعد: {{ $activity->scheduled_at->format('Y-m-d H:i') }}</span>
                                @endif
                            </div>
                            @if ($activity->description)
                                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ $activity->description }}</p>
                            @endif
                            <div class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-400 dark:text-slate-500">
                                <span>{{ $activity->creator?->name }}</span>
                                <span>{{ $activity->created_at->diffForHumans() }}</span>
                            </div>
                        </div>
                        <div class="flex shrink-0 items-center gap-1">
                            @can('permission.complete_activities')
                                @if (! $activity->isCompleted())
                                    <form method="POST" action="{{ route('activities.complete', $activity) }}">
                                        @csrf
                                        <button type="submit" class="rounded-lg border border-emerald-300 px-3 py-1.5 text-xs font-medium text-emerald-600 hover:bg-emerald-50 dark:border-emerald-500/40 dark:text-emerald-400 dark:hover:bg-emerald-500/10">{{ __('إكمال') }}</button>
                                    </form>
                                @endif
                            @endcan
                            @can('permission.edit_activities')
                                <a href="{{ route('activities.edit', $activity) }}" class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-300" title="تعديل">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                </a>
                            @endcan
                            @can('permission.delete_activities')
                                <form method="POST" action="{{ route('activities.destroy', $activity) }}" onsubmit="return confirm('حذف هذا النشاط؟')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="rounded-md p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10 dark:hover:text-rose-400" title="حذف">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                    </button>
                                </form>
                            @endcan
                        </div>
                    </li>
                @empty
                    <li>
                        <x-empty-state icon="inbox" title="{{ __('لا توجد أنشطة') }}" :description="__('لم تُسجَّل أي نشاطات بعد. ستظهر هنا تفاعلات الفريق وتحديثات العملاء.')" />
                    </li>
                @endforelse
            </ol>
            @if ($activities->hasPages())
                <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-700">{{ $activities->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>