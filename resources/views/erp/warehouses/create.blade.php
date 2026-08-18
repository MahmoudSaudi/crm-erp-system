<x-app-layout>
    <x-slot name="title">{{ __('إضافة مستودع') }}</x-slot>

    <div class="mx-auto max-w-2xl space-y-6">
        <div>
            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('إضافة مستودع') }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">أدخل بيانات المستودع الجديد</p>
        </div>

        <form method="POST" action="{{ route('warehouses.store') }}" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            @csrf

            <div class="space-y-4">
                <div>
                    <x-input-label for="name" value="اسم المستودع" />
                    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" value="{{ old('name') }}" required autofocus />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="code" value="الرمز" />
                    <x-text-input id="code" name="code" type="text" class="mt-1 block w-full" value="{{ old('code') }}" required placeholder="WH-01" />
                    <x-input-error :messages="$errors->get('code')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="address" value="العنوان (اختياري)" />
                    <x-text-input id="address" name="address" type="text" class="mt-1 block w-full" value="{{ old('address') }}" />
                    <x-input-error :messages="$errors->get('address')" class="mt-2" />
                </div>
                <div>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true)) class="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800">
                        <span class="text-sm text-slate-700 dark:text-slate-300">مستودع نشط</span>
                    </label>
                </div>
            </div>

            <div class="mt-6 flex items-center gap-3">
                <x-primary-button>{{ __('حفظ') }}</x-primary-button>
                <a href="{{ route('warehouses.index') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">{{ __('إلغاء') }}</a>
            </div>
        </form>
    </div>
</x-app-layout>