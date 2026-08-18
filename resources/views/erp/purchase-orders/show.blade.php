<x-app-layout>
    <x-slot name="title">{{ __('أمر شراء') }} {{ $order->order_number }}</x-slot>

    @php
        $status = \App\Enums\PurchaseOrderStatus::from($order->status);
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
                    @can('permission.confirm_purchase_orders')
                        <form method="POST" action="{{ route('purchase-orders.confirm', $order) }}" onsubmit="return confirm('تأكيد أمر الشراء؟')">
                            @csrf
                            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                تأكيد الأمر
                            </button>
                        </form>
                    @endcan
                    @can('permission.create_purchase_orders')
                        <a href="{{ route('purchase-orders.edit', $order) }}" class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-800">تعديل</a>
                    @endcan
                    @can('permission.cancel_purchase_orders')
                        <form method="POST" action="{{ route('purchase-orders.cancel', $order) }}" onsubmit="return confirm('إلغاء أمر الشراء؟')">
                            @csrf
                            <button type="submit" class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-800">إلغاء</button>
                        </form>
                        <form method="POST" action="{{ route('purchase-orders.destroy', $order) }}" onsubmit="return confirm('حذف أمر الشراء؟ (مسودة فقط)')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="inline-flex items-center rounded-lg border border-rose-200 px-4 py-2 text-sm font-medium text-rose-600 hover:bg-rose-50 dark:border-rose-500/30 dark:hover:bg-rose-500/10">حذف</button>
                        </form>
                    @endcan
                @elseif ($order->status === 'confirmed')
                    @can('permission.cancel_purchase_orders')
                        <form method="POST" action="{{ route('purchase-orders.cancel', $order) }}" onsubmit="return confirm('إلغاء أمر الشراء؟')">
                            @csrf
                            <button type="submit" class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-800">إلغاء</button>
                        </form>
                    @endcan
                @endif
            </div>
        </div>

        @if ($order->status === 'cancelled')
            <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300">تم إلغاء أمر الشراء.</div>
        @endif

        {{-- Details grid --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="space-y-6">
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">المورد</h3>
                    <p class="mt-2 text-lg font-bold text-slate-900 dark:text-white">{{ $order->supplier?->name }}</p>
                    @if ($order->supplier?->phone)
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400" dir="ltr">{{ $order->supplier->phone }}</p>
                    @endif
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">المستودع المستلم</h3>
                    <p class="mt-2 text-sm font-medium text-slate-700 dark:text-slate-200">{{ $order->warehouse?->name ?? '—' }}</p>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">الملخص</h3>
                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex items-center justify-between"><dt class="text-slate-500 dark:text-slate-400">عدد الأصناف</dt><dd class="font-medium text-slate-900 dark:text-white">{{ $order->items->count() }}</dd></div>
                        @if ($order->expected_date)
                            <div class="flex items-center justify-between"><dt class="text-slate-500 dark:text-slate-400">متوقع الاستلام</dt><dd class="font-medium text-slate-900 dark:text-white">{{ $order->expected_date->format('Y-m-d') }}</dd></div>
                        @endif
                        @if ($order->received_at)
                            <div class="flex items-center justify-between"><dt class="text-slate-500 dark:text-slate-400">آخر استلام</dt><dd class="font-medium text-slate-900 dark:text-white">{{ $order->received_at->format('Y-m-d H:i') }}</dd></div>
                        @endif
                        <div class="border-t border-slate-200 pt-2 dark:border-slate-700">
                            <div class="flex items-center justify-between"><dt class="text-base font-semibold text-slate-900 dark:text-white">الإجمالي</dt><dd class="text-base font-bold text-indigo-600 dark:text-indigo-400">{{ number_format((float) $order->total, 2) }} ₪</dd></div>
                        </div>
                    </dl>
                </div>
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
                                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">المطلوب</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">المستلم</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">المتبقي</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">تكلفة الوحدة</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">الإجمالي</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($order->items as $item)
                                <tr>
                                    <td class="px-4 py-3 font-medium text-slate-900 dark:text-white">{{ $item->product?->name }}</td>
                                    <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">{{ (float) $item->quantity }}</td>
                                    <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">{{ (float) $item->received_qty }}</td>
                                    <td class="px-4 py-3 text-sm {{ $item->remaining() > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400' }}">{{ $item->remaining() }}</td>
                                    <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">{{ number_format((float) $item->unit_cost, 2) }} ₪</td>
                                    <td class="px-4 py-3 text-sm font-semibold text-slate-700 dark:text-slate-200">{{ number_format((float) $item->total, 2) }} ₪</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Receive form (confirmed only) --}}
        @if ($order->status === 'confirmed' && $order->items->contains(fn ($item) => $item->remaining() > 0))
            @can('permission.receive_purchase_orders')
                <form method="POST" action="{{ route('purchase-orders.receive', $order) }}" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    @csrf
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">استلام الكميات</h3>
                        <span class="text-xs text-slate-500 dark:text-slate-400">المستودع: {{ $order->warehouse?->name }}</span>
                    </div>

                    <div class="mt-4 space-y-2">
                        @foreach ($order->items as $item)
                            @if ($item->remaining() <= 0)
                                @continue
                            @endif
                            <div class="grid grid-cols-12 gap-2 items-center rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                                <div class="col-span-5">
                                    <div class="font-medium text-slate-900 dark:text-white">{{ $item->product?->name }}</div>
                                    <div class="text-xs text-slate-400">المطلوب {{ (float) $item->quantity }} · المستلم {{ (float) $item->received_qty }} · المتبقي {{ $item->remaining() }}</div>
                                </div>
                                <div class="col-span-4">
                                    <x-text-input type="number" step="0.01" min="1" :max="$item->remaining()" :name="'quantities['.$item->id.']'" class="block w-full text-sm" placeholder="الكمية للاستلام" value="{{ $item->remaining() }}" required />
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-5">
                        <x-primary-button>{{ __('استلام والإضافة للمخزون') }}</x-primary-button>
                    </div>
                </form>
            @endcan
        @endif

        @if ($order->notes)
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">ملاحظات</h3>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ $order->notes }}</p>
            </div>
        @endif
    </div>
</x-app-layout>