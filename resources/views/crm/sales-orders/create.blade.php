<x-app-layout>
    <x-slot name="title">{{ __('إنشاء أمر بيع') }}</x-slot>

    <div class="mx-auto max-w-5xl space-y-6">
        <div>
            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('إنشاء أمر بيع') }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">أدخل بيانات الأمر وأصنافه</p>
        </div>

        <form method="POST" action="{{ route('sales-orders.store') }}" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900"
              x-data="salesOrderForm()">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <x-input-label for="customer_id" value="العميل" />
                    <select id="customer_id" name="customer_id" required class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                        <option value="">اختر العميل</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" @selected(old('customer_id') == $customer->id)>{{ $customer->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('customer_id')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="order_date" value="تاريخ الأمر" />
                    <x-text-input id="order_date" name="order_date" type="date" class="mt-1 block w-full" value="{{ old('order_date', now()->toDateString()) }}" />
                </div>
                <div>
                    <x-input-label for="delivery_date" value="تاريخ التسليم (اختياري)" />
                    <x-text-input id="delivery_date" name="delivery_date" type="date" class="mt-1 block w-full" value="{{ old('delivery_date') }}" />
                </div>
            </div>

            {{-- Items --}}
            <div class="mt-8">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">الأصناف</h3>
                    <button type="button" @click="addRow()" class="inline-flex items-center gap-1 rounded-lg bg-indigo-50 px-3 py-1.5 text-xs font-medium text-indigo-600 hover:bg-indigo-100 dark:bg-indigo-500/10 dark:text-indigo-400">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                        إضافة صنف
                    </button>
                </div>

                <div class="mt-3 space-y-2">
                    <template x-for="(row, index) in rows" :key="index">
                        <div class="grid grid-cols-12 gap-2 items-center rounded-lg border border-slate-200 p-2 dark:border-slate-700">
                            <div class="col-span-5">
                                <select :name="'items['+index+'][product_id]'" x-model.number="row.product_id" required class="block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                                    <option value="">اختر المنتج</option>
                                    @foreach ($products as $product)
                                        <option value="{{ $product->id }}" data-price="{{ $product->sale_price }}">{{ $product->name }} · {{ $product->sku }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-span-2">
                                <x-text-input type="number" step="0.01" min="0.01" x-model.number="row.quantity" x-bind:name="'items['+index+'][quantity]'" class="block w-full text-sm" placeholder="الكمية" required />
                            </div>
                            <div class="col-span-2">
                                <x-text-input type="number" step="0.01" min="0" x-model.number="row.unit_price" x-bind:name="'items['+index+'][unit_price]'" class="block w-full text-sm" placeholder="السعر" required />
                            </div>
                            <div class="col-span-2 text-sm font-medium text-slate-700 dark:text-slate-200">
                                <span x-text="(row.quantity * row.unit_price).toFixed(2)"></span> ₪
                            </div>
                            <div class="col-span-1 flex justify-end">
                                <button type="button" @click="removeRow(index)" x-show="rows.length > 1" class="rounded-md p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
                <x-input-error :messages="$errors->get('items')" class="mt-2" />
            </div>

            {{-- Totals --}}
            <div class="mt-8 grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <x-input-label for="discount_amount" value="الخصم" />
                    <x-text-input id="discount_amount" name="discount_amount" type="number" step="0.01" min="0" class="mt-1 block w-full" value="{{ old('discount_amount', 0) }}" />
                </div>
                <div>
                    <x-input-label for="shipping_amount" value="الشحن" />
                    <x-text-input id="shipping_amount" name="shipping_amount" type="number" step="0.01" min="0" class="mt-1 block w-full" value="{{ old('shipping_amount', 0) }}" />
                </div>
            </div>

            <div class="mt-4">
                <x-input-label for="notes" value="ملاحظات (اختياري)" />
                <textarea id="notes" name="notes" rows="2" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">{{ old('notes') }}</textarea>
            </div>

            <div class="mt-6 flex items-center gap-3">
                <x-primary-button>{{ __('حفظ') }}</x-primary-button>
                <a href="{{ route('sales-orders.index') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">{{ __('إلغاء') }}</a>
            </div>
        </form>
    </div>

    <script>
        function salesOrderForm() {
            return {
                rows: [{ product_id: '', quantity: 1, unit_price: '' }],
                addRow() {
                    this.rows.push({ product_id: '', quantity: 1, unit_price: '' });
                },
                removeRow(index) {
                    if (this.rows.length > 1) this.rows.splice(index, 1);
                }
            }
        }
    </script>
</x-app-layout>