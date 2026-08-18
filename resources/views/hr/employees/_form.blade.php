@php $employee ??= null; @endphp

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div>
        <x-input-label for="first_name" value="الاسم الأول" />
        <x-text-input id="first_name" name="first_name" type="text" class="mt-1 block w-full" value="{{ old('first_name', $employee?->first_name) }}" required />
        <x-input-error :messages="$errors->get('first_name')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="last_name" value="الاسم الأخير" />
        <x-text-input id="last_name" name="last_name" type="text" class="mt-1 block w-full" value="{{ old('last_name', $employee?->last_name) }}" required />
        <x-input-error :messages="$errors->get('last_name')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="email" value="البريد الإلكتروني" />
        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" value="{{ old('email', $employee?->email) }}" />
    </div>
    <div>
        <x-input-label for="phone" value="الهاتف" />
        <x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full" value="{{ old('phone', $employee?->phone) }}" />
    </div>
    <div>
        <x-input-label for="department_id" value="القسم" />
        <select id="department_id" name="department_id" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
            <option value="">بدون قسم</option>
            @foreach ($departments as $department)
                <option value="{{ $department->id }}" @selected((int) old('department_id', $employee?->department_id) === (int) $department->id)>{{ $department->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <x-input-label for="position" value="المنصب" />
        <x-text-input id="position" name="position" type="text" class="mt-1 block w-full" value="{{ old('position', $employee?->position) }}" />
    </div>
    <div>
        <x-input-label for="salary_type" value="نوع الراتب" />
        <select id="salary_type" name="salary_type" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
            <option value="fixed" @selected(old('salary_type', $employee?->salary_type ?? 'fixed') === 'fixed')>راتب ثابت</option>
            <option value="commission" @selected(old('salary_type', $employee?->salary_type ?? 'fixed') === 'commission')>عمولة</option>
            <option value="hourly" @selected(old('salary_type', $employee?->salary_type ?? 'fixed') === 'hourly')>بالساعة</option>
        </select>
        <x-input-error :messages="$errors->get('salary_type')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="base_salary" value="الراتب الأساسي (₪)" />
        <x-text-input id="base_salary" name="base_salary" type="number" step="0.01" min="0" class="mt-1 block w-full" value="{{ old('base_salary', $employee?->base_salary) }}" required />
        <x-input-error :messages="$errors->get('base_salary')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="national_id" value="الرقم الوطني" />
        <x-text-input id="national_id" name="national_id" type="text" class="mt-1 block w-full" value="{{ old('national_id', $employee?->national_id) }}" />
    </div>
    <div>
        <x-input-label for="hire_date" value="تاريخ التعيين" />
        <x-text-input id="hire_date" name="hire_date" type="date" class="mt-1 block w-full" value="{{ old('hire_date', $employee?->hire_date?->format('Y-m-d')) }}" />
    </div>
    <div>
        <x-input-label for="user_id" value="حساب المستخدم المرتبط (اختياري)" />
        <select id="user_id" name="user_id" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
            <option value="">بدون حساب</option>
            @foreach ($users as $user)
                <option value="{{ $user->id }}" @selected((int) old('user_id', $employee?->user_id) === (int) $user->id)>{{ $user->name }}</option>
            @endforeach
        </select>
    </div>
</div>

<div class="mt-4">
    <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $employee?->is_active ?? true)) class="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800" />
        موظف نشط
    </label>
</div>