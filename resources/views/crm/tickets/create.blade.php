<x-app-layout>
    <x-slot name="title">{{ __('إنشاء تذكرة') }}</x-slot>

    <div class="mx-auto max-w-3xl">
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('إنشاء تذكرة دعم') }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">سجّل طلب الدعم وأرفق التفاصيل</p>
        </div>

        <form method="POST" action="{{ route('tickets.store') }}" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="customer_id" value="العميل (اختياري)" />
                    <select id="customer_id" name="customer_id" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                        <option value="">اختر العميل</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" @selected(old('customer_id') == $customer->id)>{{ $customer->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('customer_id')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="category" value="التصنيف" />
                    <select id="category" name="category" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                        <option value="">اختر التصنيف</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category }}" @selected(old('category') === $category)>{{ $category }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('category')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="priority" value="الأولوية" />
                    <select id="priority" name="priority" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                        @foreach ($priorities as $value => $label)
                            <option value="{{ $value }}" @selected(old('priority', 'medium') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('priority')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="subject" value="الموضوع" />
                    <x-text-input id="subject" name="subject" type="text" class="mt-1 block w-full" value="{{ old('subject') }}" required />
                    <x-input-error :messages="$errors->get('subject')" class="mt-2" />
                </div>
            </div>

            <div class="mt-4">
                <x-input-label for="message" value="التفاصيل" />
                <textarea id="message" name="message" rows="4" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200" required>{{ old('message') }}</textarea>
                <x-input-error :messages="$errors->get('message')" class="mt-2" />
            </div>

            <div class="mt-6 flex items-center gap-3">
                <x-primary-button>{{ __('حفظ') }}</x-primary-button>
                <a href="{{ route('tickets.index') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">{{ __('إلغاء') }}</a>
            </div>
        </form>
    </div>
</x-app-layout>