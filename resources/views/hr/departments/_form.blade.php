@php $department ??= null; @endphp

<div>
    <x-input-label for="name" value="اسم القسم" />
    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" value="{{ old('name', $department?->name) }}" required />
    <x-input-error :messages="$errors->get('name')" class="mt-2" />
</div>