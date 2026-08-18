@php
    $opportunity = $opportunity ?? null;
    $stages = \App\Models\CRM\OpportunityStage::ordered();
    $customers = \App\Models\CRM\Customer::where('status', 'active')->orderBy('name')->get(['id', 'name']);
    $users = \App\Models\User::where('is_active', true)->get(['id', 'name']);
@endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div class="md:col-span-2">
        <x-input-label for="title" value="عنوان الفرصة *" />
        <x-text-input id="title" name="title" type="text" class="mt-1 block w-full" value="{{ old('title', $opportunity?->title) }}" required autofocus />
        <x-input-error :messages="$errors->get('title')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="customer_id" value="العميل *" />
        <select id="customer_id" name="customer_id" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
            @foreach ($customers as $customer)
                <option value="{{ $customer->id }}" @selected((string) old('customer_id', $opportunity?->customer_id) === (string) $customer->id)>{{ $customer->name }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('customer_id')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="stage_id" value="المرحلة *" />
        <select id="stage_id" name="stage_id" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
            @foreach ($stages as $stage)
                <option value="{{ $stage->id }}" @selected((string) old('stage_id', $opportunity?->stage_id) === (string) $stage->id)>{{ $stage->name }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('stage_id')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="amount" value="المبلغ المتوقع *" />
        <x-text-input id="amount" name="amount" type="number" step="0.01" min="0" class="mt-1 block w-full" value="{{ old('amount', $opportunity?->amount) }}" required />
        <x-input-error :messages="$errors->get('amount')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="probability" value="نسبة الاحتمالية (%)" />
        <x-text-input id="probability" name="probability" type="number" min="0" max="100" class="mt-1 block w-full" value="{{ old('probability', $opportunity?->probability) }}" />
        <x-input-error :messages="$errors->get('probability')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="expected_close_date" value="موعد الإغلاق المتوقع" />
        <x-text-input id="expected_close_date" name="expected_close_date" type="date" class="mt-1 block w-full" value="{{ old('expected_close_date', $opportunity?->expected_close_date?->format('Y-m-d')) }}" />
        <x-input-error :messages="$errors->get('expected_close_date')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="assigned_to" value="مندوب المبيعات" />
        <select id="assigned_to" name="assigned_to" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
            <option value="">— اختر —</option>
            @foreach ($users as $user)
                <option value="{{ $user->id }}" @selected((string) old('assigned_to', $opportunity?->assigned_to) === (string) $user->id)>{{ $user->name }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('assigned_to')" class="mt-2" />
    </div>
</div>

<div class="mt-4">
    <x-input-label for="description" value="الوصف" />
    <textarea id="description" name="description" rows="3" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">{{ old('description', $opportunity?->description) }}</textarea>
    <x-input-error :messages="$errors->get('description')" class="mt-2" />
</div>