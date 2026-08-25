<header class="sticky top-0 z-30 bg-white border-b border-slate-200 dark:bg-slate-900 dark:border-slate-800">
    <div class="flex items-center justify-between h-16 px-4 sm:px-6 lg:px-8">
        <div class="flex items-center gap-3">
            <button
                type="button"
                class="lg:hidden text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white focus:outline-none"
                @click="toggleSidebar()"
                aria-label="طيّ/فتح القائمة الجانبية"
            >
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>

            <h1 class="text-lg font-bold text-slate-900 dark:text-white">{{ $title ?? 'لوحة التحكم' }}</h1>
        </div>

        <div class="flex items-center gap-4">
            {{-- Dark mode toggle --}}
            <button
                type="button"
                class="relative inline-flex h-7 w-14 flex-shrink-0 cursor-pointer items-center rounded-full border-2 border-transparent bg-slate-200 dark:bg-slate-700 transition-colors duration-300 ease-in-out focus:outline-none"
                @click="toggleTheme()"
                aria-label="تبديل الوضع الليلي"
                title="تبديل الوضع الليلي"
            >
                <span class="sr-only">تبديل الوضع الليلي</span>
                <span 
                    class="pointer-events-none relative inline-block h-6 w-6 transform rounded-full bg-white shadow ring-0 transition duration-300 ease-in-out translate-x-0 dark:-translate-x-7"
                >
                    <span 
                        class="absolute inset-0 flex h-full w-full items-center justify-center transition-opacity duration-300 ease-in-out opacity-0 dark:opacity-100"
                        aria-hidden="true"
                    >
                        <svg class="h-3 w-3 text-slate-800" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path>
                        </svg>
                    </span>
                    <span 
                        class="absolute inset-0 flex h-full w-full items-center justify-center transition-opacity duration-300 ease-in-out opacity-100 dark:opacity-0"
                        aria-hidden="true"
                    >
                        <svg class="h-4 w-4 text-amber-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </span>
                </span>
            </button>

            {{-- Notifications --}}
            <div x-data="notificationsDropdown(@js($topNotifications->map(fn ($n) => [
                'id' => $n->id,
                'title' => $n->title,
                'time' => $n->created_at->diffForHumans(),
                'read_at' => $n->read_at,
                'read_url' => route('notifications.read', $n),
            ])->all()), {{ $topUnreadCount }})" class="relative">
                <button
                    type="button"
                    class="relative p-2 rounded-full text-slate-500 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white"
                    @click="open = !open"
                    aria-label="الإشعارات"
                >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                    <template x-if="unreadCount > 0">
                        <span class="absolute -top-1 -left-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-600 px-1.5 text-[10px] font-bold text-white" x-text="unreadCount"></span>
                    </template>
                </button>

                <div
                    x-show="open"
                    x-transition
                    @click.outside="open = false"
                    class="absolute left-0 mt-2 w-80 rounded-lg bg-white shadow-lg ring-1 ring-slate-200 z-50 dark:bg-slate-800 dark:ring-slate-700"
                    dir="rtl"
                    x-cloak
                >
                    <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100 dark:border-slate-700">
                        <div class="text-sm font-semibold text-slate-900 dark:text-white">الإشعارات</div>
                        <form method="POST" action="{{ route('notifications.read-all') }}">
                            @csrf
                            <button type="submit" class="text-xs text-indigo-600 hover:text-indigo-700 dark:text-indigo-400">تعليم الكل كمقروء</button>
                        </form>
                    </div>
                    <template x-if="items.length === 0">
                        <div class="px-4 py-8 text-center text-sm text-slate-400">لا توجد إشعارات</div>
                    </template>
                    <div class="max-h-80 overflow-y-auto">
                        <template x-for="item in items" :key="item.id">
                            <form method="POST" :action="item.read_url">
                                @csrf
                                <button type="submit" class="w-full text-right px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-700/50" :class="{ 'bg-slate-50 dark:bg-slate-700/30': !item.read_at }">
                                    <div class="flex items-start gap-2">
                                        <span x-show="!item.read_at" class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-indigo-500"></span>
                                        <div class="flex-1">
                                            <div class="text-sm font-medium text-slate-900 dark:text-white" x-text="item.title"></div>
                                            <div class="mt-0.5 text-xs text-slate-500 dark:text-slate-400" x-text="item.time"></div>
                                        </div>
                                    </div>
                                </button>
                            </form>
                        </template>
                    </div>
                    <div class="border-t border-slate-100 px-4 py-2 dark:border-slate-700">
                        <a href="{{ route('notifications.index') }}" class="block text-center text-xs font-medium text-indigo-600 hover:text-indigo-700 dark:text-indigo-400">عرض كل الإشعارات</a>
                    </div>
                </div>
            </div>


        </div>
    </div>

    <script>
        function notificationsDropdown(items = [], unreadCount = 0) {
            return {
                open: false,
                unreadCount,
                items,
            };
        }
    </script>
</header>