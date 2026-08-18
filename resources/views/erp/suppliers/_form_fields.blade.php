@php $supplier ??= null; @endphp

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div>
        <x-input-label for="name" value="اسم المورد" />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" value="{{ old('name', $supplier?->name) }}" required />
        <x-input-error :messages="$errors->get('name')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="contact_name" value="اسم جهة الاتصال (اختياري)" />
        <x-text-input id="contact_name" name="contact_name" type="text" class="mt-1 block w-full" value="{{ old('contact_name', $supplier?->contact_name) }}" />
    </div>
    <div>
        <x-input-label for="phone" value="الهاتف" />
        <x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full" value="{{ old('phone', $supplier?->phone) }}" />
    </div>
    <div>
        <x-input-label for="email" value="البريد الإلكتروني" />
        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" value="{{ old('email', $supplier?->email) }}" />
    </div>
    <div>
        <x-input-label for="tax_number" value="الرقم الضريبي" />
        <x-text-input id="tax_number" name="tax_number" type="text" class="mt-1 block w-full" value="{{ old('tax_number', $supplier?->tax_number) }}" />
    </div>
    <div class="sm:col-span-2">
        <x-input-label for="address" value="العنوان" />
        <x-text-input id="address" name="address" type="text" class="mt-1 block w-full" value="{{ old('address', $supplier?->address) }}" />
    </div>
</div>

<div class="mt-4">
    <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $supplier?->is_active ?? true)) class="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800" />
        مورد نشط
    </label>
</div>
