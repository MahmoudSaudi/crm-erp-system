<x-app-layout>
    <div class="max-w-7xl mx-auto">
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden dark:bg-slate-900 dark:border-slate-800">
            <div class="flex flex-col items-center justify-center py-20 px-6 text-center">
                <div class="flex items-center justify-center w-20 h-20 rounded-2xl bg-indigo-50 text-indigo-600 mb-6 dark:bg-indigo-500/20 dark:text-indigo-300">
                    <svg class="w-10 h-10" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                </div>

                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ $title }}</h2>
                <p class="mt-2 text-slate-500 max-w-md dark:text-slate-400">
                    هذه الوحدة مدرجة في خطة المشروع وسيتم بناؤها في المراحل القادمة وفق خطة الـ Milestones.
                </p>

                <div class="mt-6 inline-flex items-center gap-2 rounded-full bg-amber-50 border border-amber-200 px-4 py-1.5 text-sm text-amber-700">
                    <span class="relative flex h-2.5 w-2.5">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-amber-500"></span>
                    </span>
                    قيد التطوير — مرحلة قادمة
                </div>
            </div>
        </div>
    </div>
</x-app-layout>