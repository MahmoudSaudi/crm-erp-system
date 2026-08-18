<x-app-layout>
    <x-slot name="title">{{ __('أمر بيع') }} {{ $order->order_number }}</x-slot>

    @php
        $status = \App\Enums\SalesOrderStatus::from($order->status);
        $totalItems = $order->items->sum('quantity');
    @endphp

    <div class="mx-auto max-w-5xl space-y-6">
        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <h2 class="text-2xl font-bold text-slate-900 dark:text-white"><span class="font-mono" dir="ltr">{{ $order->order_number }}</span></h2>
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $status?->color() }}">{{ $status?->label() }}</span>
                </div>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">أُنشئ في {{ $order->order_date?->format('Y-m-d') }} بواسطة {{ $order->creator?->name ?? '—' }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if ($order->status === 'draft')
                    @can('permission.confirm_sales_orders')
                        <form method="POST" action="{{ route('sales-orders.confirm', $order) }}" onsubmit="return confirm('سيتم خصم المخزون تلقائيًا. متابعة؟')">
                            @csrf
                            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                تأكيد الأمر
                            </button>
                        </form>
                    @endcan
                    <a href="{{ route('sales-orders.edit', $order) }}" class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-800">تعديل</a>
                    <form method="POST" action="{{ route('sales-orders.destroy', $order) }}" onsubmit="return confirm('حذف أمر البيع؟')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="inline-flex items-center rounded-lg border border-rose-200 px-4 py-2 text-sm font-medium text-rose-600 hover:bg-rose-50 dark:border-rose-500/30 dark:hover:bg-rose-500/10">حذف</button>
                    </form>
                @elseif ($order->status === 'confirmed')
                    @can('permission.fulfill_sales_orders')
                        <form method="POST" action="{{ route('sales-orders.fulfill', $order) }}" onsubmit="return confirm('تحديد الأمر كمنفّذ؟')">
                            @csrf
                            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                تنفيذ الأمر
                            </button>
                        </form>
                    @endcan
                @endif
                @if (in_array($order->status, ['draft', 'confirmed']))
                    @can('permission.cancel_sales_orders')
                        <form method="POST" action="{{ route('sales-orders.cancel', $order) }}" onsubmit="return confirm('إلغاء أمر البيع؟')">
                            @csrf
                            <button type="submit" class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-800">إلغاء</button>
                        </form>
                    @endcan
                @endif
            </div>
        </div>

        @if ($order->status === 'cancelled')
            <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300">
                تم إلغاء هذا الأمر البيع. {{ $order->cancelled_at ? 'في ' . $order->cancelled_at->format('Y-m-d H:i') : '' }}
            </div>
        @endif

        {{-- Details grid --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Customer + summary --}}
            <div class="space-y-6">
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">العميل</h3>
                    <a href="{{ route('customers.show', $order->customer) }}" class="mt-2 block text-lg font-bold text-indigo-600 hover:text-indigo-700 dark:text-indigo-400">{{ $order->customer?->name }}</a>
                    @if ($order->customer?->phone)
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400" dir="ltr">{{ $order->customer->phone }}</p>
                    @endif
                    @if ($order->customer?->email)
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400" dir="ltr">{{ $order->customer->email }}</p>
                    @endif
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">الملخص</h3>
                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex items-center justify-between"><dt class="text-slate-500 dark:text-slate-400">عدد الأصناف</dt><dd class="font-medium text-slate-900 dark:text-white">{{ $order->items->count() }}</dd></div>
                        <div class="flex items-center justify-between"><dt class="text-slate-500 dark:text-slate-400">إجمالي الكميات</dt><dd class="font-medium text-slate-900 dark:text-white">{{ $totalItems }}</dd></div>
                        <div class="flex items-center justify-between"><dt class="text-slate-500 dark:text-slate-400">الخصم</dt><dd class="font-medium text-slate-900 dark:text-white">{{ number_format((float) $order->discount_amount, 2) }} ₪</dd></div>
                        <div class="flex items-center justify-between"><dt class="text-slate-500 dark:text-slate-400">الشحن</dt><dd class="font-medium text-slate-900 dark:text-white">{{ number_format((float) $order->shipping_amount, 2) }} ₪</dd></div>
                        <div class="border-t border-slate-200 pt-2 dark:border-slate-700">
                            <div class="flex items-center justify-between"><dt class="text-base font-semibold text-slate-900 dark:text-white">الإجمالي</dt><dd class="text-base font-bold text-indigo-600 dark:text-indigo-400">{{ number_format((float) $order->total, 2) }} ₪</dd></div>
                        </div>
                    </dl>
                </div>

                @if ($order->invoice)
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">الفاتورة</h3>
                        <a href="{{ route('invoices.show', $order->invoice) }}" class="mt-2 inline-flex items-center gap-2 font-mono text-indigo-600 hover:text-indigo-700 dark:text-indigo-400" dir="ltr">
                            {{ $order->invoice->invoice_number }}
                        </a>
                    </div>
                @endif
            </div>

            {{-- Items table --}}
            <div class="lg:col-span-2 rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-700">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">الأصناف</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                        <thead class="bg-slate-50 dark:bg-slate-800/60">
                            <tr>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">المنتج</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">الكمية</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">سعر الوحدة</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">الإجمالي</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($order->items as $item)
                                <tr>
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-slate-900 dark:text-white">{{ $item->product_name }}</div>
                                        @if ($item->product)
                                            <div class="text-xs text-slate-400 dark:text-slate-500" dir="ltr">{{ $item->product->sku }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">{{ (float) $item->quantity }}</td>
                                    <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">{{ number_format((float) $item->unit_price, 2) }} ₪</td>
                                    <td class="px-4 py-3 text-sm font-semibold text-slate-700 dark:text-slate-200">{{ number_format((float) $item->total, 2) }} ₪</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @if ($order->notes)
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">ملاحظات</h3>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ $order->notes }}</p>
            </div>
        @endif
    </div>
</x-app-layout>