@php
    $lead = $lead ?? null;
    $statuses = \App\Enums\LeadStatus::list();
@endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <x-input-label for="first_name" value="الاسم الأول *" />
        <x-text-input id="first_name" name="first_name" type="text" class="mt-1 block w-full" value="{{ old('first_name', $lead?->first_name) }}" required autofocus />
        <x-input-error :messages="$errors->get('first_name')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="last_name" value="اسم العائلة" />
        <x-text-input id="last_name" name="last_name" type="text" class="mt-1 block w-full" value="{{ old('last_name', $lead?->last_name) }}" />
        <x-input-error :messages="$errors->get('last_name')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="phone" value="رقم الهاتف" />
        <x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full" value="{{ old('phone', $lead?->phone) }}" />
        <x-input-error :messages="$errors->get('phone')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="email" value="البريد الإلكتروني" />
        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" value="{{ old('email', $lead?->email) }}" />
        <x-input-error :messages="$errors->get('email')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="company" value="الشركة" />
        <x-text-input id="company" name="company" type="text" class="mt-1 block w-full" value="{{ old('company', $lead?->company) }}" />
        <x-input-error :messages="$errors->get('company')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="source" value="المصدر" />
        <x-text-input id="source" name="source" type="text" class="mt-1 block w-full" value="{{ old('source', $lead?->source) }}" placeholder="إعلانات، توصيات، وسائل تواصل..." />
        <x-input-error :messages="$errors->get('source')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="status" value="الحالة" />
        <select id="status" name="status" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
            @foreach ($statuses as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $lead?->status ?? 'new') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('status')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="value" value="القيمة المتوقعة (₪)" />
        <x-text-input id="value" name="value" type="number" step="0.01" min="0" class="mt-1 block w-full" value="{{ old('value', $lead?->value) }}" />
        <x-input-error :messages="$errors->get('value')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="assigned_to" value="مندوب المبيعات" />
        <select id="assigned_to" name="assigned_to" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
            <option value="">— اختر —</option>
            @foreach ($users as $user)
                <option value="{{ $user->id }}" @selected((string) old('assigned_to', $lead?->assigned_to) === (string) $user->id)>{{ $user->name }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('assigned_to')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="next_follow_up_at" value="متابعة قادمة" />
        <x-text-input id="next_follow_up_at" name="next_follow_up_at" type="date" class="mt-1 block w-full" value="{{ old('next_follow_up_at', $lead?->next_follow_up_at?->format('Y-m-d')) }}" />
        <x-input-error :messages="$errors->get('next_follow_up_at')" class="mt-2" />
    </div>
</div>

<div class="mt-4">
    <x-input-label for="notes" value="ملاحظات" />
    <textarea id="notes" name="notes" rows="3" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">{{ old('notes', $lead?->notes) }}</textarea>
    <x-input-error :messages="$errors->get('notes')" class="mt-2" />
</div>