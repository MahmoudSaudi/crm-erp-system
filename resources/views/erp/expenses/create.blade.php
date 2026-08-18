<x-app-layout>
    <x-slot name="title">{{ __('تسجيل مصروف') }}</x-slot>

    <div class="mx-auto max-w-3xl space-y-6">
        <div>
            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('تسجيل مصروف') }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">سجّل مصروفًا تشغيليًا للشركة</p>
        </div>

        <form method="POST" action="{{ route('expenses.store') }}" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="category" value="التصنيف" />
                    <select id="category" name="category" required class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                        <option value="">اختر التصنيف</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category }}" @selected(old('category') === $category)>{{ $category }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('category')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="amount" value="المبلغ" />
                    <x-text-input id="amount" name="amount" type="number" step="0.01" min="0" class="mt-1 block w-full" value="{{ old('amount') }}" placeholder="0.00" required />
                    <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="date" value="التاريخ" />
                    <x-text-input id="date" name="date" type="date" class="mt-1 block w-full" value="{{ old('date', now()->toDateString()) }}" required />
                    <x-input-error :messages="$errors->get('date')" class="mt-2" />
                </div>
                <div class="flex items-end pb-1">
                    <label class="flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-200">
                        <input type="checkbox" name="is_reimbursable" value="1" @checked(old('is_reimbursable')) class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800">
                        قابل للاسترداد
                    </label>
                </div>
            </div>

            <div class="mt-4">
                <x-input-label for="description" value="الوصف" />
                <x-text-input id="description" name="description" type="text" class="mt-1 block w-full" value="{{ old('description') }}" placeholder="وصف المصروف (مثال: فاتورة كهرباء)" />
                <x-input-error :messages="$errors->get('description')" class="mt-2" />
            </div>

            <div class="mt-6 flex items-center gap-3">
                <x-primary-button>{{ __('حفظ') }}</x-primary-button>
                <a href="{{ route('expenses.index') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">{{ __('إلغاء') }}</a>
            </div>
        </form>
    </div>
</x-app-layout>