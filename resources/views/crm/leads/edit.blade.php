<x-app-layout>
    <x-slot name="title">{{ __('تعديل عميل محتمل') }}</x-slot>

    <div class="mx-auto max-w-3xl space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('تعديل عميل محتمل') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $lead->full_name }}</p>
            </div>
            <a href="{{ route('leads.index') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">{{ __('رجوع') }}</a>
        </div>

        <form method="POST" action="{{ route('leads.update', $lead) }}" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            @csrf @method('PATCH')
            @include('crm.leads._form_fields')

            <div class="mt-6 flex items-center gap-3">
                <x-primary-button>{{ __('حفظ التغييرات') }}</x-primary-button>
                <a href="{{ route('leads.index') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">{{ __('إلغاء') }}</a>
            </div>
        </form>
    </div>
</x-app-layout>