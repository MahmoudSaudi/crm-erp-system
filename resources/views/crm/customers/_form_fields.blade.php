@php
    $customer = $customer ?? null;
@endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <x-input-label for="name" value="اسم العميل *" />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" value="{{ old('name', $customer?->name) }}" required autofocus />
        <x-input-error :messages="$errors->get('name')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="type" value="النوع *" />
        <select id="type" name="type" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
            <option value="individual" @selected(old('type', $customer?->type ?? 'individual') === 'individual')>فرد</option>
            <option value="company" @selected(old('type', $customer?->type) === 'company')>شركة</option>
        </select>
        <x-input-error :messages="$errors->get('type')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="phone" value="رقم الهاتف" />
        <x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full" value="{{ old('phone', $customer?->phone) }}" />
        <x-input-error :messages="$errors->get('phone')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="email" value="البريد الإلكتروني" />
        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" value="{{ old('email', $customer?->email) }}" />
        <x-input-error :messages="$errors->get('email')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="company" value="اسم الشركة" />
        <x-text-input id="company" name="company" type="text" class="mt-1 block w-full" value="{{ old('company', $customer?->company) }}" />
        <x-input-error :messages="$errors->get('company')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="tax_number" value="الرقم الضريبي" />
        <x-text-input id="tax_number" name="tax_number" type="text" class="mt-1 block w-full" value="{{ old('tax_number', $customer?->tax_number) }}" />
        <x-input-error :messages="$errors->get('tax_number')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="city" value="المدينة" />
        <x-text-input id="city" name="city" type="text" class="mt-1 block w-full" value="{{ old('city', $customer?->city) }}" />
        <x-input-error :messages="$errors->get('city')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="credit_limit" value="الحد الائتماني (₪)" />
        <x-text-input id="credit_limit" name="credit_limit" type="number" step="0.01" min="0" class="mt-1 block w-full" value="{{ old('credit_limit', $customer?->credit_limit) }}" />
        <x-input-error :messages="$errors->get('credit_limit')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="status" value="الحالة *" />
        <select id="status" name="status" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
            <option value="active" @selected(old('status', $customer?->status ?? 'active') === 'active')>نشط</option>
            <option value="inactive" @selected(old('status', $customer?->status) === 'inactive')>غير نشط</option>
        </select>
        <x-input-error :messages="$errors->get('status')" class="mt-2" />
    </div>
</div>

<div class="mt-4">
    <x-input-label for="address" value="العنوان" />
    <textarea id="address" name="address" rows="2" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">{{ old('address', $customer?->address) }}</textarea>
    <x-input-error :messages="$errors->get('address')" class="mt-2" />
</div>