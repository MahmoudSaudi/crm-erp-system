<x-app-layout>
    <x-slot name="title">{{ __('تفاصيل الراتب') }}</x-slot>

    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('تفاصيل الراتب') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">فترة {{ $payroll->period }}</p>
            </div>
            <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium {{ $payroll->statusColor() }}">{{ $payroll->statusLabel() }}</span>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <div class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-700">
                        <h3 class="font-semibold text-slate-900 dark:text-white">بيانات الموظف</h3>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-5 text-sm">
                        <div>
                            <div class="text-slate-500 dark:text-slate-400">الاسم</div>
                            <div class="mt-1 font-medium text-slate-900 dark:text-white">{{ $payroll->employee?->first_name }} {{ $payroll->employee?->last_name }}</div>
                        </div>
                        <div>
                            <div class="text-slate-500 dark:text-slate-400">المنصب</div>
                            <div class="mt-1 font-medium text-slate-900 dark:text-white">{{ $payroll->employee?->position ?? '—' }}</div>
                        </div>
                        <div>
                            <div class="text-slate-500 dark:text-slate-400">القسم</div>
                            <div class="mt-1 font-medium text-slate-900 dark:text-white">{{ $payroll->employee?->department?->name ?? '—' }}</div>
                        </div>
                        <div>
                            <div class="text-slate-500 dark:text-slate-400">الفترة</div>
                            <div class="mt-1 font-medium text-slate-900 dark:text-white">{{ $payroll->period_start?->format('Y-m-d') }} ← {{ $payroll->period_end?->format('Y-m-d') }}</div>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-700">
                        <h3 class="font-semibold text-slate-900 dark:text-white">مكونات الراتب</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                            <thead class="bg-slate-50 dark:bg-slate-800/60">
                                <tr>
                                    <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">البند</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">المبلغ</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @forelse ($payroll->items as $item)
                                    <tr>
                                        <td class="px-5 py-3 text-sm text-slate-700 dark:text-slate-200">{{ $item->label }}</td>
                                        <td class="px-5 py-3 text-sm font-medium text-slate-700 dark:text-slate-200">{{ number_format((float) $item->amount, 2) }} ₪</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="2" class="px-5 py-8 text-center text-sm text-slate-500 dark:text-slate-400">لا توجد بنود</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <h3 class="mb-4 font-semibold text-slate-900 dark:text-white">الملخص المالي</h3>
                    <dl class="space-y-3 text-sm">
                        <div class="flex items-center justify-between">
                            <dt class="text-slate-500 dark:text-slate-400">الراتب الأساسي</dt>
                            <dd class="font-medium text-slate-900 dark:text-white">{{ number_format((float) $payroll->base_salary, 2) }} ₪</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-slate-500 dark:text-slate-400">البدلات</dt>
                            <dd class="font-medium text-emerald-600 dark:text-emerald-400">+ {{ number_format((float) $payroll->allowances, 2) }} ₪</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-slate-500 dark:text-slate-400">المكافأة</dt>
                            <dd class="font-medium text-emerald-600 dark:text-emerald-400">+ {{ number_format((float) $payroll->bonus, 2) }} ₪</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-slate-500 dark:text-slate-400">الخصومات</dt>
                            <dd class="font-medium text-rose-600 dark:text-rose-400">− {{ number_format((float) $payroll->deductions, 2) }} ₪</dd>
                        </div>
                        <div class="flex items-center justify-between border-t border-slate-200 pt-3 dark:border-slate-700">
                            <dt class="font-semibold text-slate-900 dark:text-white">الصافي</dt>
                            <dd class="text-lg font-bold text-indigo-600 dark:text-indigo-400">{{ number_format((float) $payroll->net_total, 2) }} ₪</dd>
                        </div>
                        @if ($payroll->paid_at)
                            <div class="flex items-center justify-between">
                                <dt class="text-slate-500 dark:text-slate-400">تاريخ الصرف</dt>
                                <dd class="font-medium text-slate-900 dark:text-white">{{ $payroll->paid_at?->format('Y-m-d') }}</dd>
                            </div>
                        @endif
                        @if ($payroll->notes)
                            <div class="rounded-lg bg-slate-50 p-3 text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $payroll->notes }}</div>
                        @endif
                    </dl>
                </div>

                @if ($payroll->status === 'draft')
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                        <h3 class="mb-4 font-semibold text-slate-900 dark:text-white">تعديل المكونات</h3>
                        <form method="POST" action="{{ route('payroll.update', $payroll) }}" class="space-y-4">
                            @csrf
                            @method('PUT')
                            <div>
                                <x-input-label for="allowances" value="البدلات" />
                                <x-text-input id="allowances" name="allowances" type="number" step="0.01" min="0" class="mt-1 block w-full" value="{{ old('allowances', $payroll->allowances) }}" />
                            </div>
                            <div>
                                <x-input-label for="bonus" value="المكافأة" />
                                <x-text-input id="bonus" name="bonus" type="number" step="0.01" min="0" class="mt-1 block w-full" value="{{ old('bonus', $payroll->bonus) }}" />
                            </div>
                            <div>
                                <x-input-label for="deductions" value="الخصومات" />
                                <x-text-input id="deductions" name="deductions" type="number" step="0.01" min="0" class="mt-1 block w-full" value="{{ old('deductions', $payroll->deductions) }}" />
                            </div>
                            <div>
                                <x-input-label for="notes" value="ملاحظات" />
                                <textarea id="notes" name="notes" rows="3" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">{{ old('notes', $payroll->notes) }}</textarea>
                            </div>
                            <x-primary-button class="w-full justify-center">حفظ المكونات</x-primary-button>
                        </form>
                    </div>

                    <div class="space-y-2">
                        @can('permission.approve_payroll')
                            <form method="POST" action="{{ route('payroll.approve', $payroll) }}">
                                @csrf
                                <button type="submit" class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-700">
                                    اعتماد الراتب
                                </button>
                            </form>
                        @endcan
                        @can('permission.run_payroll')
                            <form method="POST" action="{{ route('payroll.destroy', $payroll) }}" onsubmit="return confirm('هل تريد حذف هذا الراتب؟')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="w-full inline-flex items-center justify-center gap-2 rounded-lg border border-rose-200 px-4 py-2 text-sm font-medium text-rose-600 hover:bg-rose-50 dark:border-rose-500/30 dark:hover:bg-rose-500/10">
                                    حذف الراتب
                                </button>
                            </form>
                        @endcan
                    </div>
                @elseif ($payroll->status === 'approved')
                    @can('permission.pay_payroll')
                        <form method="POST" action="{{ route('payroll.pay', $payroll) }}">
                            @csrf
                            <button type="submit" class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                                صرف الراتب
                            </button>
                        </form>
                    @endcan
                @endif
            </div>
        </div>
    </div>
</x-app-layout>