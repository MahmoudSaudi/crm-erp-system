<x-app-layout>
    <x-slot name="title">{{ __('أوامر البيع') }}</x-slot>

    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('أوامر البيع') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">إنشاء أوامر البيع وتتبع تأكيدها وتنفيذها</p>
            </div>
            @can('permission.create_sales_orders')
                <a href="{{ route('sales-orders.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                    {{ __('إنشاء أمر بيع') }}
                </a>
            @endcan
        </div>

        {{-- Filters --}}
        <form method="GET" action="{{ route('sales-orders.index') }}" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <x-input-label for="search" value="بحث" />
                    <x-text-input id="search" name="search" type="text" class="mt-1 block w-full" value="{{ request('search') }}" placeholder="رقم الأمر أو اسم العميل..." />
                </div>
                <div>
                    <x-input-label for="status" value="الحالة" />
                    <select id="status" name="status" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                        <option value="">كل الحالات</option>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected($activeStatus === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                        {{ __('تصفية') }}
                    </button>
                    @if (request()->hasAny(['search', 'status']))
                        <a href="{{ route('sales-orders.index') }}" class="ms-2 inline-flex items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-800">{{ __('مسح') }}</a>
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
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">رقم الأمر</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">العميل</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">التاريخ</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">الإجمالي</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">الحالة</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">فاتورة</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($orders as $order)
                            @php $orderStatus = \App\Enums\SalesOrderStatus::from($order->status); @endphp
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="px-4 py-3">
                                    <a href="{{ route('sales-orders.show', $order) }}" class="font-medium text-indigo-600 hover:text-indigo-700 dark:text-indigo-400" dir="ltr">{{ $order->order_number }}</a>
                                </td>
                                <td class="px-4 py-3 text-sm font-medium text-slate-900 dark:text-white">{{ $order->customer?->name }}</td>
                                <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">{{ $order->order_date->format('Y-m-d') }}</td>
                                <td class="px-4 py-3 text-sm font-semibold text-slate-700 dark:text-slate-200">{{ number_format((float) $order->total, 2) }} ₪</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $orderStatus?->color() }}">{{ $orderStatus?->label() }}</span>
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">
                                    @if ($order->invoice)
                                        <a href="{{ route('invoices.show', $order->invoice) }}" class="font-medium text-indigo-600 dark:text-indigo-400" dir="ltr">{{ $order->invoice->invoice_number }}</a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-1">
                                        <a href="{{ route('sales-orders.show', $order) }}" class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-300" title="عرض">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0zm-9 0a9 9 0 0118 0 9 9 0 01-18 0z" /></svg>
                                        </a>
                                        @can('permission.create_sales_orders')
                                            @if ($order->status === 'draft')
                                                <a href="{{ route('sales-orders.edit', $order) }}" class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-300" title="تعديل">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                                </a>
                                            @endif
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <x-empty-state icon="inbox" title="{{ __('لا توجد أوامر بيع بعد') }}" :description="__('أنشئ أمر بيع جديدًا من عملاء مؤكدين.')" />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($orders->hasPages())
                <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-700">{{ $orders->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>