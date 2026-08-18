<x-app-layout>
    <x-slot name="title">{{ $customer->name }}</x-slot>

    <div class="space-y-6" x-data="{ tab: 'overview' }">
        {{-- Header --}}
        <div class="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-4">
                <div class="flex h-14 w-14 items-center justify-center rounded-xl bg-indigo-100 text-xl font-bold text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-300">
                    {{ mb_substr($customer->name, 0, 1) }}
                </div>
                <div>
                    <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ $customer->name }}</h2>
                    <div class="mt-1 flex flex-wrap items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600 dark:bg-slate-700 dark:text-slate-300">{{ $customer->type === 'company' ? 'شركة' : 'فرد' }}</span>
                        @if ($customer->status === 'active')
                            <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300">نشط</span>
                        @endif
                        @if ($customer->company)
                            <span>{{ $customer->company }}</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="flex gap-2">
                @can('permission.edit_customers')
                    <a href="{{ route('customers.edit', $customer) }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                        {{ __('تعديل') }}
                    </a>
                @endcan
            </div>
        </div>

        {{-- Contact info --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div class="text-xs font-medium text-slate-500 dark:text-slate-400">الهاتف</div>
                <div class="mt-1 font-semibold text-slate-800 dark:text-slate-100" dir="ltr">{{ $customer->phone ?: '—' }}</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div class="text-xs font-medium text-slate-500 dark:text-slate-400">البريد الإلكتروني</div>
                <div class="mt-1 truncate font-semibold text-slate-800 dark:text-slate-100" dir="ltr">{{ $customer->email ?: '—' }}</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div class="text-xs font-medium text-slate-500 dark:text-slate-400">رصيد العميل</div>
                <div class="mt-1 font-semibold {{ (float) $customer->balance > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' }}">{{ number_format((float) $customer->balance, 2) }} ₪</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div class="text-xs font-medium text-slate-500 dark:text-slate-400">الموقع</div>
                <div class="mt-1 font-semibold text-slate-800 dark:text-slate-100">{{ $customer->city ?: '—' }}</div>
            </div>
        </div>

        {{-- Tabs --}}
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="flex border-b border-slate-200 px-2 dark:border-slate-700">
                @php
                    $tabs = [
                        ['key' => 'overview', 'label' => 'نظرة عامة'],
                        ['key' => 'opportunities', 'label' => 'الفرص ('.$customer->opportunities->count().')'],
                        ['key' => 'sales_orders', 'label' => 'أوامر البيع ('.$customer->salesOrders->count().')'],
                        ['key' => 'invoices', 'label' => 'الفواتير ('.$customer->invoices->count().')'],
                        ['key' => 'payments', 'label' => 'المدفوعات ('.$customer->payments->count().')'],
                    ];
                @endphp
                @foreach ($tabs as $tabItem)
                    <button
                        type="button"
                        @click="tab = '{{ $tabItem['key'] }}'"
                        x-bind:class="tab === '{{ $tabItem['key'] }}' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'"
                        class="mx-1 border-b-2 px-4 py-3 text-sm font-medium transition"
                    >{{ $tabItem['label'] }}</button>
                @endforeach
            </div>

            <div class="p-6">
                {{-- Overview --}}
                <div x-show="tab === 'overview'">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div class="rounded-lg bg-slate-50 p-4 dark:bg-slate-800/60">
                            <div class="text-sm text-slate-500 dark:text-slate-400">عدد الفرص</div>
                            <div class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">{{ $customer->opportunities->count() }}</div>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-4 dark:bg-slate-800/60">
                            <div class="text-sm text-slate-500 dark:text-slate-400">قيمة الفرص المفتوحة</div>
                            <div class="mt-1 text-2xl font-bold text-indigo-600 dark:text-indigo-400">
                                {{ number_format((float) $customer->opportunities->sum(fn ($o) => $o->amount), 0) }} ₪
                            </div>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-4 dark:bg-slate-800/60">
                            <div class="text-sm text-slate-500 dark:text-slate-400">دين مستحق</div>
                            <div class="mt-1 text-2xl font-bold text-rose-600 dark:text-rose-400">{{ number_format((float) $customer->invoices->sum('remaining_amount'), 2) }} ₪</div>
                        </div>
                    </div>

                    @if ($customer->created_from_lead_id)
                        <div class="mt-4 rounded-lg bg-indigo-50 p-4 text-sm text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">
                            هذا العميل تم إنشاؤه من عميل محتمل
                            @if ($customer->createdFromLead)
                                ({{ $customer->createdFromLead->full_name }})
                            @endif
                        </div>
                    @endif

                    @if ($customer->address)
                        <div class="mt-4 text-sm text-slate-600 dark:text-slate-300"><strong>العنوان:</strong> {{ $customer->address }}</div>
                    @endif
                    @if ($customer->tax_number)
                        <div class="mt-2 text-sm text-slate-600 dark:text-slate-300"><strong>الرقم الضريبي:</strong> {{ $customer->tax_number }}</div>
                    @endif
                </div>

                {{-- Opportunities --}}
                <div x-show="tab === 'opportunities'" x-cloak>
                    @forelse ($customer->opportunities as $opportunity)
                        <div class="flex items-center justify-between rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                            <div>
                                <a href="{{ route('opportunities.show', $opportunity) }}" class="font-semibold text-slate-900 hover:text-indigo-600 dark:text-white dark:hover:text-indigo-400">{{ $opportunity->title }}</a>
                                <div class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                    {{ $opportunity->stage?->name }} · {{ $opportunity->assignedTo?->name }}
                                </div>
                            </div>
                            <div class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ number_format((float) $opportunity->amount, 0) }} ₪</div>
                        </div>
                    @empty
                        <div class="rounded-lg border-2 border-dashed border-slate-300 py-12 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">لا توجد فرص لهذا العميل</div>
                    @endforelse
                </div>

                {{-- Sales orders --}}
                <div x-show="tab === 'sales_orders'" x-cloak>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                            <thead>
                                <tr class="text-right text-xs font-semibold text-slate-500 dark:text-slate-400">
                                    <th class="py-2 pr-3">رقم الطلب</th>
                                    <th class="py-2 pr-3">التاريخ</th>
                                    <th class="py-2 pr-3">الإجمالي</th>
                                    <th class="py-2 pr-3">الحالة</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @forelse ($customer->salesOrders as $order)
                                    <tr class="text-sm text-slate-700 dark:text-slate-300">
                                        <td class="py-2 pr-3">{{ $order->order_number }}</td>
                                        <td class="py-2 pr-3">{{ $order->created_at->format('Y-m-d') }}</td>
                                        <td class="py-2 pr-3 font-medium">{{ number_format((float) $order->total, 2) }} ₪</td>
                                        <td class="py-2 pr-3">{{ $order->status }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="py-8 text-center text-slate-500 dark:text-slate-400">لا توجد أوامر بيع</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Invoices --}}
                <div x-show="tab === 'invoices'" x-cloak>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                            <thead>
                                <tr class="text-right text-xs font-semibold text-slate-500 dark:text-slate-400">
                                    <th class="py-2 pr-3">رقم الفاتورة</th>
                                    <th class="py-2 pr-3">التاريخ</th>
                                    <th class="py-2 pr-3">الإجمالي</th>
                                    <th class="py-2 pr-3">المتبقي</th>
                                    <th class="py-2 pr-3">الحالة</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @forelse ($customer->invoices as $invoice)
                                    <tr class="text-sm text-slate-700 dark:text-slate-300">
                                        <td class="py-2 pr-3">{{ $invoice->invoice_number }}</td>
                                        <td class="py-2 pr-3">{{ $invoice->issue_date?->format('Y-m-d') }}</td>
                                        <td class="py-2 pr-3 font-medium">{{ number_format((float) $invoice->total, 2) }} ₪</td>
                                        <td class="py-2 pr-3">{{ number_format((float) ($invoice->total - $invoice->paid_amount), 2) }} ₪</td>
                                        <td class="py-2 pr-3">{{ $invoice->status }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="py-8 text-center text-slate-500 dark:text-slate-400">لا توجد فواتير</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Payments --}}
                <div x-show="tab === 'payments'" x-cloak>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                            <thead>
                                <tr class="text-right text-xs font-semibold text-slate-500 dark:text-slate-400">
                                    <th class="py-2 pr-3">التاريخ</th>
                                    <th class="py-2 pr-3">المبلغ</th>
                                    <th class="py-2 pr-3">الطريقة</th>
                                    <th class="py-2 pr-3">الحالة</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @forelse ($customer->payments as $payment)
                                    <tr class="text-sm text-slate-700 dark:text-slate-300">
                                        <td class="py-2 pr-3">{{ $payment->paid_at?->format('Y-m-d') }}</td>
                                        <td class="py-2 pr-3 font-medium">{{ number_format((float) $payment->amount, 2) }} ₪</td>
                                        <td class="py-2 pr-3">{{ $payment->method }}</td>
                                        <td class="py-2 pr-3">{{ $payment->status }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="py-8 text-center text-slate-500 dark:text-slate-400">لا توجد مدفوعات</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>