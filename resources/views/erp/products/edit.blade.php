<x-app-layout>
    <x-slot name="title">{{ __('تعديل منتج') }}</x-slot>

    <div class="mx-auto max-w-3xl space-y-6">
        <div>
            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('تعديل منتج') }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $product->name }} · <span dir="ltr">{{ $product->sku }}</span></p>
        </div>

        <form method="POST" action="{{ route('products.update', $product) }}" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            @csrf @method('PUT')

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <x-input-label for="name" value="اسم المنتج" />
                    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" value="{{ old('name', $product->name) }}" required autofocus />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="sku" value="SKU" />
                    <x-text-input id="sku" name="sku" type="text" class="mt-1 block w-full" value="{{ old('sku', $product->sku) }}" required />
                    <x-input-error :messages="$errors->get('sku')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="barcode" value="الباركود (اختياري)" />
                    <x-text-input id="barcode" name="barcode" type="text" class="mt-1 block w-full" value="{{ old('barcode', $product->barcode) }}" />
                    <x-input-error :messages="$errors->get('barcode')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="category_id" value="التصنيف (اختياري)" />
                    <select id="category_id" name="category_id" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                        <option value="">بدون تصنيف</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected(old('category_id', $product->category_id) == $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="unit_id" value="الوحدة (اختياري)" />
                    <select id="unit_id" name="unit_id" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                        <option value="">بدون وحدة</option>
                        @foreach ($units as $unit)
                            <option value="{{ $unit->id }}" @selected(old('unit_id', $product->unit_id) == $unit->id)>{{ $unit->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="sale_price" value="سعر البيع" />
                    <x-text-input id="sale_price" name="sale_price" type="number" step="0.01" min="0" class="mt-1 block w-full" value="{{ old('sale_price', $product->sale_price) }}" />
                    <x-input-error :messages="$errors->get('sale_price')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="purchase_cost" value="تكلفة الشراء (اختياري)" />
                    <x-text-input id="purchase_cost" name="purchase_cost" type="number" step="0.01" min="0" class="mt-1 block w-full" value="{{ old('purchase_cost', $product->purchase_cost) }}" />
                    <x-input-error :messages="$errors->get('purchase_cost')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="min_stock" value="حد أدنى للمخزون" />
                    <x-text-input id="min_stock" name="min_stock" type="number" min="0" class="mt-1 block w-full" value="{{ old('min_stock', $product->min_stock) }}" />
                    <x-input-error :messages="$errors->get('min_stock')" class="mt-2" />
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="description" value="الوصف (اختياري)" />
                    <textarea id="description" name="description" rows="3" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">{{ old('description', $product->description) }}</textarea>
                    <x-input-error :messages="$errors->get('description')" class="mt-2" />
                </div>
                <div class="sm:col-span-2">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->is_active)) class="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800">
                        <span class="text-sm text-slate-700 dark:text-slate-300">منتج نشط</span>
                    </label>
                </div>
            </div>

            <div class="mt-6 flex items-center gap-3">
                <x-primary-button>{{ __('حفظ') }}</x-primary-button>
                <a href="{{ route('products.index') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">{{ __('إلغاء') }}</a>
            </div>
        </form>

        {{-- Stock levels --}}
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">المخزون الحالي</h3>
            <div class="mt-4 space-y-3">
                @forelse ($product->stockItems as $item)
                    <div class="flex items-center justify-between rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                        <div>
                            <div class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ $item->warehouse?->name }}</div>
                            <div class="text-xs text-slate-500 dark:text-slate-400">الحد الأدنى: {{ $product->min_stock }}</div>
                        </div>
                        <span class="text-lg font-bold {{ $item->quantity > 0 && (float) $item->quantity <= $product->min_stock ? 'text-amber-600 dark:text-amber-400' : 'text-slate-900 dark:text-white' }}">{{ number_format((float) $item->quantity, 2) }}</span>
                    </div>
                @empty
                    <p class="text-sm text-slate-500 dark:text-slate-400">لا يوجد مخزون مسجل لهذا المنتج.</p>
                @endforelse
            </div>

            @can('permission.adjust_stock')
                <div class="mt-6 border-t border-slate-200 pt-6 dark:border-slate-700">
                    <h4 class="font-semibold text-slate-900 dark:text-white">تحديث المخزون (إضافة / خصم / جرد)</h4>
                    <form method="POST" action="{{ route('products.stock', $product) }}" class="mt-3 grid grid-cols-1 sm:grid-cols-4 gap-3">
                        @csrf
                        <div>
                            <select name="warehouse_id" required class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                                @foreach ($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <select name="action" required class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                                <option value="in">إضافة</option>
                                <option value="out">خصم</option>
                                <option value="adjust">جرد (ضبط للكمية المدخلة)</option>
                            </select>
                        </div>
                        <div>
                            <x-text-input name="quantity" type="number" step="0.01" min="0" class="mt-1 block w-full" placeholder="الكمية" required />
                        </div>
                        <div>
                            <button type="submit" class="inline-flex w-full items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">تنفيذ</button>
                        </div>
                        <div class="sm:col-span-4">
                            <x-text-input name="note" type="text" class="mt-1 block w-full" placeholder="ملاحظة (اختياري)" />
                        </div>
                    </form>
                </div>
            @endcan
        </div>
    </div>
</x-app-layout>