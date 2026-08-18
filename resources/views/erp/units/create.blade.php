<x-app-layout>
    <x-slot name="title">{{ __('إضافة وحدة قياس') }}</x-slot>

    <div class="mx-auto max-w-2xl space-y-6">
        <div>
            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('إضافة وحدة قياس') }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">أدخل اسم الوحدة ورمزها المختصر</p>
        </div>

        <form method="POST" action="{{ route('units.store') }}" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            @csrf

            <div class="space-y-4">
                <div>
                    <x-input-label for="name" value="اسم الوحدة" />
                    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" value="{{ old('name') }}" required autofocus placeholder="قطعة، كرتونة، متر..." />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="code" value="الرمز" />
                    <x-text-input id="code" name="code" type="text" class="mt-1 block w-full" value="{{ old('code') }}" required placeholder="PCS، CTN، M..." />
                    <x-input-error :messages="$errors->get('code')" class="mt-2" />
                </div>
            </div>

            <div class="mt-6 flex items-center gap-3">
                <x-primary-button>{{ __('حفظ') }}</x-primary-button>
                <a href="{{ route('units.index') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">{{ __('إلغاء') }}</a>
            </div>
        </form>
    </div>
</x-app-layout>
