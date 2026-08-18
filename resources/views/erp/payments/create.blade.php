<x-app-layout>
    <x-slot name="title">{{ __('تسجيل دفعة') }}</x-slot>

    <div class="mx-auto max-w-3xl space-y-6">
        <div>
            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('تسجيل دفعة') }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">سجّل دفعة مستلمة على فاتورة</p>
        </div>

        @if ($invoices->isEmpty())
            <div class="rounded-xl border border-slate-200 bg-white p-10 text-center shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <svg class="mx-auto h-12 w-12 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                <h3 class="mt-4 text-base font-semibold text-slate-900 dark:text-white">لا توجد فواتير بمبالغ مستحقة</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">جميع الفواتير مسددة حاليًا</p>
                <a href="{{ route('invoices.index') }}" class="mt-4 inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">عرض الفواتير</a>
            </div>
        @else
            <form method="POST" action="{{ route('payments.store') }}" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                @csrf

                <div>
                    <x-input-label for="invoice_id" value="الفاتورة" />
                    <select id="invoice_id" name="invoice_id" required class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                        <option value="">اختر الفاتورة</option>
                        @foreach ($invoices as $invoice)
                            <option value="{{ $invoice->id }}" @selected((int) old('invoice_id', $selectedInvoice ?? 0) === $invoice->id)>
                                {{ $invoice->invoice_number }} · {{ $invoice->customer?->name }} · المتبقي {{ number_format((float) $invoice->dueAmount(), 2) }} ₪
                            </option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('invoice_id')" class="mt-2" />
                </div>

                <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="amount" value="المبلغ" />
                        <x-text-input id="amount" name="amount" type="number" step="0.01" min="0.01" class="mt-1 block w-full" value="{{ old('amount') }}" placeholder="0.00" required />
                        <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="method" value="طريقة الدفع" />
                        <select id="method" name="method" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                            @foreach ($methods as $value => $label)
                                <option value="{{ $value }}" @selected(old('method') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('method')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="paid_at" value="تاريخ الدفع" />
                        <x-text-input id="paid_at" name="paid_at" type="date" class="mt-1 block w-full" value="{{ old('paid_at', now()->toDateString()) }}" />
                    </div>
                    <div>
                        <x-input-label for="reference" value="رقم المرجع (اختياري)" />
                        <x-text-input id="reference" name="reference" type="text" class="mt-1 block w-full" value="{{ old('reference') }}" placeholder="رقم التحويل / الشيك..." />
                    </div>
                </div>

                <div class="mt-4">
                    <x-input-label for="notes" value="ملاحظات (اختياري)" />
                    <textarea id="notes" name="notes" rows="2" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">{{ old('notes') }}</textarea>
                </div>

                <div class="mt-6 flex items-center gap-3">
                    <x-primary-button>{{ __('تسجيل الدفعة') }}</x-primary-button>
                    <a href="{{ route('payments.index') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">{{ __('إلغاء') }}</a>
                </div>
            </form>
        @endif
    </div>
</x-app-layout>