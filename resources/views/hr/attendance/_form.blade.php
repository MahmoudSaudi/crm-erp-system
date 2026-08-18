@php $attendance ??= null; @endphp

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div>
        <x-input-label for="employee_id" value="الموظف" />
        <select id="employee_id" name="employee_id" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
            <option value="">اختر الموظف</option>
            @foreach ($employees as $employee)
                <option value="{{ $employee->id }}" @selected((int) old('employee_id', $attendance?->employee_id) === (int) $employee->id)>{{ $employee->first_name }} {{ $employee->last_name }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('employee_id')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="date" value="التاريخ" />
        <x-text-input id="date" name="date" type="date" class="mt-1 block w-full" value="{{ old('date', $attendance?->date?->format('Y-m-d')) }}" required />
        <x-input-error :messages="$errors->get('date')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="status" value="الحالة" />
        <select id="status" name="status" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
            @foreach ($statuses as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $attendance?->status) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('status')" class="mt-2" />
    </div>
    <div class="grid grid-cols-2 gap-4">
        <div>
            <x-input-label for="check_in" value="وقت الدخول" />
            <x-text-input id="check_in" name="check_in" type="time" class="mt-1 block w-full" value="{{ old('check_in', $attendance?->check_in?->format('H:i')) }}" />
        </div>
        <div>
            <x-input-label for="check_out" value="وقت الخروج" />
            <x-text-input id="check_out" name="check_out" type="time" class="mt-1 block w-full" value="{{ old('check_out', $attendance?->check_out?->format('H:i')) }}" />
        </div>
    </div>
</div>