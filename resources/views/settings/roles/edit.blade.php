<x-app-layout>
    <x-slot name="title">{{ __('تعديل دور') }}</x-slot>

    <div class="mx-auto max-w-3xl space-y-6">
        <div>
            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('تعديل دور') }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                {{ $role->is_system ? 'دور نظامي — لا يمكن تعديل معرفه أو حذفه' : 'قم بتعديل بيانات الدور وصلاحياته' }}
            </p>
        </div>

        <form method="POST" action="{{ route('roles.update', $role) }}" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            @csrf
            @method('PUT')

            <div class="space-y-4">
                <div>
                    <x-input-label for="name" value="اسم الدور *" />
                    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" value="{{ old('name', $role->name) }}" required autofocus />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="slug" value="المعرف (slug) *" />
                    <x-text-input id="slug" name="slug" type="text" dir="ltr" class="mt-1 block w-full" value="{{ old('slug', $role->slug) }}" required {{ $role->is_system ? 'disabled' : '' }} />
                    @if ($role->is_system)
                        <input type="hidden" name="slug" value="{{ $role->slug }}" />
                    @endif
                    <x-input-error :messages="$errors->get('slug')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="description" value="الوصف" />
                    <textarea id="description" name="description" rows="2" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">{{ old('description', $role->description) }}</textarea>
                    <x-input-error :messages="$errors->get('description')" class="mt-2" />
                </div>

                <div>
                    <div class="flex items-center justify-between">
                        <x-input-label value="الصلاحيات" />
                        <button type="button" id="toggle-all" class="text-xs font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">تحديد الكل / إزالة</button>
                    </div>
                    <div class="mt-2 max-h-96 space-y-4 overflow-y-auto rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                        @foreach ($groups as $group => $permissions)
                            <div>
                                <h4 class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $group }}</h4>
                                <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    @foreach ($permissions as $permission)
                                        <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                                            <input type="checkbox" class="permission-check rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800" name="permissions[]" value="{{ $permission->id }}" @checked(in_array($permission->id, old('permissions', $selected))) />
                                            {{ $permission->name }}
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <x-input-error :messages="$errors->get('permissions')" class="mt-2" />
                </div>
            </div>

            <div class="mt-6 flex items-center gap-3">
                <x-primary-button>{{ __('حفظ') }}</x-primary-button>
                <a href="{{ route('roles.index') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">{{ __('إلغاء') }}</a>
            </div>
        </form>
    </div>

    @push('scripts')
        <script>
            document.getElementById('toggle-all')?.addEventListener('click', function () {
                const boxes = document.querySelectorAll('.permission-check');
                const allChecked = [...boxes].every(b => b.checked);
                boxes.forEach(b => { b.checked = !allChecked; });
            });
        </script>
    @endpush
</x-app-layout>