<x-app-layout>
    <x-slot name="title">{{ $user ? __('تعديل مستخدم') : __('إضافة مستخدم') }}</x-slot>

    <div class="mx-auto max-w-2xl space-y-6">
        <div>
            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ $user ? __('تعديل مستخدم') : __('إضافة مستخدم') }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $user ? 'بتحديث بيانات حساب '.$user->name : 'بإنشاء حساب جديد لمنحه صلاحية دخول النظام' }}</p>
        </div>

        <form method="POST" action="{{ $user ? route('users.update', $user) : route('users.store') }}" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            @csrf
            @if ($user)
                @method('PUT')
            @endif

            <div class="space-y-4">
                <div>
                    <x-input-label for="name" value="الاسم الكامل *" />
                    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" value="{{ old('name', $user?->name) }}" required autofocus />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="email" value="البريد الإلكتروني *" />
                    <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" value="{{ old('email', $user?->email) }}" required />
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="password" value="{{ $user ? 'كلمة المرور الجديدة (اتركها فارغة لتبقى دون تغيير)' : 'كلمة المرور *' }}" />
                    <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" autocomplete="new-password" />
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="password_confirmation" value="تأكيد كلمة المرور" />
                    <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" autocomplete="new-password" />
                    <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="role_id" value="الدور *" />
                    <select id="role_id" name="role_id" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}" @selected(old('role_id', $user?->role_id) == $role->id)>{{ $role->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('role_id')" class="mt-2" />
                </div>

                <div class="flex items-center gap-3">
                    <input id="is_active" name="is_active" type="checkbox" value="1" class="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800" @checked(old('is_active', $user?->is_active ?? true)) />
                    <x-input-label for="is_active" value="حساب نشط" />
                </div>
            </div>

            <div class="mt-6 flex items-center gap-3">
                <x-primary-button>{{ __('حفظ') }}</x-primary-button>
                <a href="{{ route('users.index') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">{{ __('إلغاء') }}</a>
            </div>
        </form>
    </div>
</x-app-layout>