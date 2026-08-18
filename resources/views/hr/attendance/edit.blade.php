<x-app-layout>
    <x-slot name="title">{{ __('تعديل سجل حضور') }}</x-slot>

    <div class="mx-auto max-w-3xl">
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('تعديل سجل حضور') }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">حدّث حالة حضور الموظف</p>
        </div>

        <form method="POST" action="{{ route('attendance.update', $attendance) }}" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            @csrf
            @method('PUT')
            @include('hr.attendance._form')

            <div class="mt-6 flex items-center gap-3">
                <x-primary-button>{{ __('حفظ') }}</x-primary-button>
                <a href="{{ route('attendance.index') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">{{ __('إلغاء') }}</a>
            </div>
        </form>
    </div>
</x-app-layout>