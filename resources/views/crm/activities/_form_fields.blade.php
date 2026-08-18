@php
    $activity = $activity ?? null;
    $types = \App\Enums\ActivityType::list();
@endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <x-input-label for="type" value="النوع *" />
        <select id="type" name="type" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-sky-500 focus:ring-sky-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
            @foreach ($types as $value => $label)
                <option value="{{ $value }}" @selected(old('type', $activity?->type ?? 'call') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('type')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="subject" value="الموضوع *" />
        <x-text-input id="subject" name="subject" type="text" class="mt-1 block w-full" value="{{ old('subject', $activity?->subject) }}" required autofocus />
        <x-input-error :messages="$errors->get('subject')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="related_type" value="ربط بـ" />
        <select id="related_type" name="related_type" x-data class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-sky-500 focus:ring-sky-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
            <option value="">بدون ربط</option>
            <option value="{{ \App\Models\CRM\Lead::class }}" @selected(old('related_type', $activity?->related_type) === \App\Models\CRM\Lead::class)>عميل محتمل</option>
            <option value="{{ \App\Models\CRM\Customer::class }}" @selected(old('related_type', $activity?->related_type) === \App\Models\CRM\Customer::class)>عميل</option>
            <option value="{{ \App\Models\CRM\Opportunity::class }}" @selected(old('related_type', $activity?->related_type) === \App\Models\CRM\Opportunity::class)>فرصة</option>
        </select>
        <x-input-error :messages="$errors->get('related_type')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="related_id" value="العنصر المرتبط" />
        <select id="related_id" name="related_id" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-sky-500 focus:ring-sky-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
            <option value="">—</option>
            <optgroup label="العملاء المحتملون">
                @foreach ($leads as $lead)
                    <option value="{{ $lead->id }}" data-type="{{ \App\Models\CRM\Lead::class }}" @selected((string) old('related_id', $activity?->related_id) === (string) $lead->id && ($activity?->related_type ?? '') === \App\Models\CRM\Lead::class)>{{ $lead->full_name }}</option>
                @endforeach
            </optgroup>
            <optgroup label="العملاء">
                @foreach ($customers as $customer)
                    <option value="{{ $customer->id }}" data-type="{{ \App\Models\CRM\Customer::class }}" @selected((string) old('related_id', $activity?->related_id) === (string) $customer->id && ($activity?->related_type ?? '') === \App\Models\CRM\Customer::class)>{{ $customer->name }}</option>
                @endforeach
            </optgroup>
            <optgroup label="الفرص">
                @foreach ($opportunities as $opportunity)
                    <option value="{{ $opportunity->id }}" data-type="{{ \App\Models\CRM\Opportunity::class }}" @selected((string) old('related_id', $activity?->related_id) === (string) $opportunity->id && ($activity?->related_type ?? '') === \App\Models\CRM\Opportunity::class)>{{ $opportunity->title }}</option>
                @endforeach
            </optgroup>
        </select>
        <x-input-error :messages="$errors->get('related_id')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="scheduled_at" value="الموعد" />
        <x-text-input id="scheduled_at" name="scheduled_at" type="datetime-local" class="mt-1 block w-full" value="{{ old('scheduled_at', $activity?->scheduled_at?->format('Y-m-d\TH:i')) }}" />
        <x-input-error :messages="$errors->get('scheduled_at')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="assigned_to" value="المسؤول" />
        <select id="assigned_to" name="assigned_to" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-sky-500 focus:ring-sky-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
            <option value="">— اختر —</option>
            @foreach (\App\Models\User::where('is_active', true)->get(['id', 'name']) as $user)
                <option value="{{ $user->id }}" @selected((string) old('assigned_to', $activity?->assigned_to) === (string) $user->id)>{{ $user->name }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('assigned_to')" class="mt-2" />
    </div>
</div>

<div class="mt-4">
    <x-input-label for="description" value="الوصف" />
    <textarea id="description" name="description" rows="3" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-sky-500 focus:ring-sky-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">{{ old('description', $activity?->description) }}</textarea>
    <x-input-error :messages="$errors->get('description')" class="mt-2" />
</div>