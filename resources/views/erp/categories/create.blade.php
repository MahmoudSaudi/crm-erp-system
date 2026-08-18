<x-app-layout>
    <x-slot name="title">{{ __('إضافة تصنيف') }}</x-slot>

    <div class="mx-auto max-w-2xl space-y-6">
        <div>
            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('إضافة تصنيف') }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">أدخل اسم التصنيف الجديد</p>
        </div>

        <form method="POST" action="{{ route('categories.store') }}" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            @csrf

            <div class="space-y-4">
                <div>
                    <x-input-label for="name" value="اسم التصنيف" />
                    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" value="{{ old('name') }}" required autofocus />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="parent_id" value="التصنيف الأب (اختياري)" />
                    <select id="parent_id" name="parent_id" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                        <option value="">بدون تصنيف أب</option>
                        @foreach ($categories as $parent)
                            <option value="{{ $parent->id }}" @selected(old('parent_id') == $parent->id)>{{ $parent->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mt-6 flex items-center gap-3">
                <x-primary-button>{{ __('حفظ') }}</x-primary-button>
                <a href="{{ route('categories.index') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">{{ __('إلغاء') }}</a>
            </div>
        </form>
    </div>
</x-app-layout>
