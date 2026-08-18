<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ isset($title) ? $title.' | '.config('app.name') : config('app.name') }}</title>

        <script>
            (function () {
                var saved = localStorage.getItem('theme');
                var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                if (saved === 'dark' || (!saved && prefersDark)) {
                    document.documentElement.classList.add('dark');
                }
            })();
        </script>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=cairo:300,400,500,600,700,800&display=swap" rel="stylesheet" />
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Styles -->
        @vite(['resources/css/app.css'])
    </head>    <body class="font-sans antialiased bg-slate-100 text-slate-800 dark:bg-slate-950 dark:text-slate-200">
        <div x-data="layout()" class="min-h-screen">
            @include('layouts.partials.sidebar')

            {{-- Backdrop (mobile فقط) --}}
            <div
                x-show="sidebarOpen && !isDesktop"
                x-transition.opacity
                class="fixed inset-0 z-30 bg-black/50 lg:hidden"
                @click="sidebarOpen = false"
                x-cloak
            ></div>

            <div x-bind:class="isDesktop && !collapsed ? 'lg:pr-72' : ''">
                @include('layouts.partials.topbar')

                <main class="px-4 sm:px-6 lg:px-8 py-6" dir="rtl">
                    {{-- Flash messages --}}
                    @if (session('success'))
                        <div class="mb-4 flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300">
                            <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            <span>{{ session('success') }}</span>
                        </div>
                    @endif
                    @if (session('error'))
                        <div class="mb-4 flex items-start gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300">
                            <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            <span>{{ session('error') }}</span>
                        </div>
                    @endif

                    {{ $slot }}
                </main>

                <footer class="px-6 pb-6 text-center text-xs text-slate-400 dark:text-slate-600">
                    {{ config('app.name') }} — نظام متكامل لإدارة علاقات العملاء والموارد المؤسسية
                </footer>
            </div>
        </div>

        @vite(['resources/js/app.js'])
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    </body>
</html>