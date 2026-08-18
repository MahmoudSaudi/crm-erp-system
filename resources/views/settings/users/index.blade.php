<x-app-layout>
    <x-slot name="title">{{ __('المستخدمون') }}</x-slot>

    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('المستخدمون') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">إدارة حسابات مستخدمي النظام وأدوارهم</p>
            </div>
            @can('permission.create_users')
                <a href="{{ route('users.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                    {{ __('إضافة مستخدم') }}
                </a>
            @endcan
        </div>

        {{-- Filters --}}
        <form method="GET" action="{{ route('users.index') }}" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <x-input-label for="search" value="بحث" />
                    <x-text-input id="search" name="search" type="text" class="mt-1 block w-full" value="{{ request('search') }}" placeholder="الاسم، البريد..." />
                </div>
                <div>
                    <x-input-label for="role_id" value="الدور" />
                    <select id="role_id" name="role_id" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                        <option value="">كل الأدوار</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}" @selected($activeRole == $role->id)>{{ $role->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="is_active" value="الحالة" />
                    <select id="is_active" name="is_active" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                        <option value="">الكل</option>
                        <option value="1" @selected($activeStatus === '1')>نشط</option>
                        <option value="0" @selected($activeStatus === '0')>معطل</option>
                    </select>
                </div>
                <div class="sm:col-span-3 flex items-end gap-2">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600">
                        {{ __('تصفية') }}
                    </button>
                    @if (request()->hasAny(['search', 'role_id', 'is_active']))
                        <a href="{{ route('users.index') }}" class="ms-2 inline-flex items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-800">{{ __('مسح') }}</a>
                    @endif
                </div>
            </div>
        </form>

        {{-- Table --}}
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-800/60">
                        <tr>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">الاسم</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">البريد الإلكتروني</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">الدور</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">الحالة</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">آخر دخول</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($users as $user)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-slate-900 dark:text-white">{{ $user->name }}</div>
                                    @if ($user->id === auth()->id())
                                        <div class="text-xs text-indigo-500">أنت</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300" dir="ltr">{{ $user->email }}</td>
                                <td class="px-4 py-3"><span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300">{{ $user->role?->name ?: '—' }}</span></td>
                                <td class="px-4 py-3">
                                    @if ($user->is_active)
                                        <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400">نشط</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-medium text-rose-700 dark:bg-rose-500/15 dark:text-rose-400">معطل</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">{{ $user->last_login_at ? $user->last_login_at->format('d/m/Y H:i') : '—' }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-1">
                                        @can('permission.edit_users')
                                            <a href="{{ route('users.edit', $user) }}" class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-300" title="تعديل">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                            </a>
                                        @endcan
                                        @can('permission.delete_users')
                                            @if ($user->id !== auth()->id())
                                                <form method="POST" action="{{ route('users.destroy', $user) }}" onsubmit="return confirm('حذف هذا المستخدم؟')">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="rounded-md p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10 dark:hover:text-rose-400" title="حذف">
                                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                                    </button>
                                                </form>
                                            @endif
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <x-empty-state icon="inbox" title="{{ __('لا يوجد مستخدمون بعد') }}" :description="__('أضِف أول مستخدم لمنحه صلاحية الدخول للنظام وربطه بدور مناسب.')" />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($users->hasPages())
                <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-700">{{ $users->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>