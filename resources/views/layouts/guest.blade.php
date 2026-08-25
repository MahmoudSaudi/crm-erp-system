<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'CRM ERP') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=cairo:300,400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased text-slate-900 dark:text-slate-100 bg-slate-50 dark:bg-slate-900">
        <div class="min-h-screen flex">
            
            <!-- Right Side (Brand Area) -->
            <div class="hidden lg:flex lg:w-1/2 relative bg-gradient-to-br from-indigo-900 via-indigo-800 to-slate-900 overflow-hidden items-center justify-center">
                <!-- Abstract Background Shapes -->
                <div class="absolute inset-0 opacity-20 pointer-events-none">
                    <svg class="absolute left-0 top-0 h-full w-full" viewBox="0 0 100 100" preserveAspectRatio="none">
                        <polygon points="0,100 100,0 100,100" fill="currentColor" class="text-indigo-950"></polygon>
                    </svg>
                    <svg class="absolute right-0 top-0 h-1/2 w-1/2" viewBox="0 0 100 100" fill="none">
                        <circle cx="50" cy="0" r="50" class="fill-indigo-600 blur-3xl opacity-50"></circle>
                    </svg>
                </div>
                
                <!-- Content -->
                <div class="relative z-10 flex flex-col items-center text-center px-12">
                    <x-application-logo class="w-40 h-40 mb-8 drop-shadow-2xl" />
                    <h1 class="text-5xl font-extrabold text-white mb-4 tracking-tight">Nexus</h1>
                    <p class="text-lg text-indigo-200 font-medium max-w-md leading-relaxed">
                        نظام متكامل واحترافي لإدارة علاقات العملاء والموارد المؤسسية بكفاءة عالية.
                    </p>
                </div>
            </div>

            <!-- Left Side (Form Area) -->
            <div class="w-full lg:w-1/2 flex items-center justify-center p-6 sm:p-12">
                <div class="w-full max-w-md">
                    
                    <!-- Mobile Logo (Visible only on small screens) -->
                    <div class="lg:hidden flex flex-col items-center mb-8">
                        <x-application-logo class="w-20 h-20 mb-4 drop-shadow-md" />
                        <h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Nexus</h1>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-2">إدارة متكاملة لعملك</p>
                    </div>

                    <!-- The Form Slot -->
                    <div class="bg-white dark:bg-slate-800 shadow-2xl rounded-3xl p-8 border border-slate-100 dark:border-slate-700/50">
                        {{ $slot }}
                    </div>
                    
                </div>
            </div>
            
        </div>
    </body>
</html>