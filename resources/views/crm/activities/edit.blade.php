<x-app-layout>
    <x-slot name="title">{{ __('تعديل نشاط') }}</x-slot>

    <div class="mx-auto max-w-3xl space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('تعديل نشاط') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $activity->subject }}</p>
            </div>
            <a href="{{ route('activities.index') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">{{ __('رجوع') }}</a>
        </div>

        <form method="POST" action="{{ route('activities.update', $activity) }}" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            @csrf @method('PATCH')
            @include('crm.activities._form_fields')

            <div class="mt-6 flex items-center gap-3">
                <x-primary-button>{{ __('حفظ التغييرات') }}</x-primary-button>
                <a href="{{ route('activities.index') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">{{ __('إلغاء') }}</a>
            </div>
        </form>
    </div>
</x-app-layout>