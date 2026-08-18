<x-app-layout>
    <x-slot name="title">{{ __('توليد رواتب') }}</x-slot>

    <div class="mx-auto max-w-3xl">
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('توليد رواتب') }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">أنشئ مسودات رواتب لكل الموظفين النشطين لفترة محددة</p>
        </div>

        <form method="POST" action="{{ route('payroll.run') }}" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            @csrf
            <div>
                <x-input-label for="period" value="الفترة (شهر)" />
                <x-text-input id="period" name="period" type="month" class="mt-1 block w-full" value="{{ old('period', now()->format('Y-m')) }}" required />
                <x-input-error :messages="$errors->get('period')" class="mt-2" />
            </div>
            <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">سيتم تخطي الموظفين الذين لديهم راتب لهذه الفترة مسبقًا.</p>

            <div class="mt-6 flex items-center gap-3">
                <x-primary-button>{{ __('توليد الرواتب') }}</x-primary-button>
                <a href="{{ route('payroll.index') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">{{ __('إلغاء') }}</a>
            </div>
        </form>
    </div>
</x-app-layout>