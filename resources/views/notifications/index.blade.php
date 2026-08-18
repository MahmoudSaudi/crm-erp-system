<x-app-layout>
    <x-slot name="title">{{ __('الإشعارات') }}</x-slot>

    <div class="mx-auto max-w-3xl space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('الإشعارات') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">كل التنبيهات الداخلية الخاصة بك</p>
            </div>
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                <button type="submit" class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-800">تعليم الكل كمقروء</button>
            </form>
        </div>

        <div class="space-y-2">
            @forelse ($notifications as $notification)
                <form method="POST" action="{{ route('notifications.read', $notification) }}" class="block rounded-xl border p-4 shadow-sm {{ $notification->read_at ? 'border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900' : 'border-indigo-200 bg-indigo-50/60 dark:border-indigo-500/20 dark:bg-indigo-500/5' }}">
                    @csrf
                    <button type="submit" class="w-full text-right">
                        <div class="flex items-start gap-2">
                            <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $notification->read_at ? 'bg-transparent' : 'bg-indigo-500' }}"></span>
                            <div class="flex-1">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="text-sm font-semibold text-slate-900 dark:text-white">{{ $notification->title }}</div>
                                    <span class="shrink-0 text-xs text-slate-400">{{ $notification->created_at->diffForHumans() }}</span>
                                </div>
                                @if ($notification->body)
                                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-300 line-clamp-2">{{ $notification->body }}</p>
                                @endif
                                @if ($notification->link)
                                    <span class="mt-2 inline-block text-xs font-medium text-indigo-600 dark:text-indigo-400">فتح →</span>
                                @endif
                            </div>
                        </div>
                    </button>
                </form>
            @empty
                <div>
                    <x-empty-state icon="inbox" title="{{ __('لا توجد إشعارات') }}" :description="__('لا توجد إشعارات حتى الآن. ستظهر هنا التنبيهات عند توفرها.')" />
                </div>
            @endforelse
        </div>

        @if ($notifications->hasPages())
            <div>{{ $notifications->links() }}</div>
        @endif
    </div>
</x-app-layout>