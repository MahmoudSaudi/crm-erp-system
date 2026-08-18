<x-app-layout>
    <x-slot name="title">{{ __('إنشاء فاتورة') }}</x-slot>

    <div class="mx-auto max-w-4xl space-y-6">
        <div>
            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('إنشاء فاتورة') }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">أنشئ فاتورة من أمر بيع مؤكد</p>
        </div>

        @if ($orders->isEmpty())
            <div class="rounded-xl border border-slate-200 bg-white p-10 text-center shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <svg class="mx-auto h-12 w-12 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                <h3 class="mt-4 text-base font-semibold text-slate-900 dark:text-white">لا توجد أوامر بيع مؤكدة متاحة</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">يجب تأكيد أمر بيع أولًا لإنشاء فاتورة منه</p>
                <a href="{{ route('sales-orders.index') }}" class="mt-4 inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">عرض أوامر البيع</a>
            </div>
        @else
            <form method="POST" action="{{ route('invoices.store') }}" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900"
                  x-data="{ orderId: @js((int) ($selectedOrder ?? $orders->first()->id)) }">
                @csrf

                <div>
                    <x-input-label for="order_id" value="أمر البيع" />
                    <select id="order_id" name="order_id" required x-model.number="orderId" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                        @foreach ($orders as $order)
                            <option value="{{ $order->id }}">{{ $order->order_number }} · {{ $order->customer?->name }} · {{ number_format((float) $order->total, 2) }} ₪</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('order_id')" class="mt-2" />
                </div>

                <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="issue_date" value="تاريخ الإصدار" />
                        <x-text-input id="issue_date" name="issue_date" type="date" class="mt-1 block w-full" value="{{ old('issue_date', now()->toDateString()) }}" />
                    </div>
                    <div>
                        <x-input-label for="due_date" value="تاريخ الاستحقاق" />
                        <x-text-input id="due_date" name="due_date" type="date" class="mt-1 block w-full" value="{{ old('due_date', now()->addDays(15)->toDateString()) }}" />
                    </div>
                </div>

                <div class="mt-4">
                    <x-input-label for="notes" value="ملاحظات (اختياري)" />
                    <textarea id="notes" name="notes" rows="2" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">{{ old('notes') }}</textarea>
                </div>

                <div class="mt-6 flex items-center gap-3">
                    <x-primary-button>{{ __('إنشاء الفاتورة') }}</x-primary-button>
                    <a href="{{ route('invoices.index') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">{{ __('إلغاء') }}</a>
                </div>
            </form>
        @endif
    </div>
</x-app-layout>