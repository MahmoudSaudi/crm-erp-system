<x-app-layout>
    <x-slot name="title">{{ __('كانبان الفرص') }}</x-slot>

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('كانبان الفرص') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">اسحب البطاقات لتغيير مرحلة الفرصة</p>
            </div>
            <a href="{{ route('opportunities.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2m-6-2v4m6-4v4" /></svg>
                {{ __('عرض الجدول') }}
            </a>
        </div>

        <div class="flex gap-4 overflow-x-auto pb-4">
            @foreach ($stages as $stage)
                <div
                    class="w-80 shrink-0 rounded-xl bg-slate-100/80 p-3 dark:bg-slate-800/50"
                    x-data="{ over: false }"
                    @dragover.prevent="over = true"
                    @dragleave="over = false"
                    @drop.prevent="
                        const id = event.dataTransfer.getData('text/plain');
                        if (id) {
                            fetch('{{ route('opportunities.update-stage', ['opportunity' => '__ID__']) }}'.replace('__ID__', id), {
                                method: 'PATCH',
                                headers: {
                                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json'
                                },
                                body: JSON.stringify({ stage_id: '{{ $stage->id }}' })
                            }).then(r => r.ok ? window.location.reload() : null);
                        }
                        over = false;
                    "
                    x-bind:class="over ? 'ring-2 ring-violet-400' : ''"
                >
                    <div class="mb-3 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="h-2.5 w-2.5 rounded-full" style="background: {{ $stage->color }}"></span>
                            <h3 class="font-semibold text-slate-700 dark:text-slate-200">{{ $stage->name }}</h3>
                            @if ($stage->is_won)
                                <span class="text-xs text-emerald-500">✓</span>
                            @endif
                        </div>
                        <span class="rounded-full bg-white px-2 py-0.5 text-xs font-semibold text-slate-500 shadow-sm dark:bg-slate-700 dark:text-slate-300">
                            {{ count($opportunitiesByStage[$stage->id] ?? []) }}
                        </span>
                    </div>

                    <div class="space-y-3">
                        @forelse ($opportunitiesByStage[$stage->id] ?? [] as $opportunity)
                            <div
                                draggable="true"
                                @dragstart="event.dataTransfer.setData('text/plain', '{{ $opportunity->id }}')"
                                class="cursor-grab rounded-lg border border-slate-200 bg-white p-3 shadow-sm active:cursor-grabbing dark:border-slate-700 dark:bg-slate-900"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <a href="{{ route('opportunities.show', $opportunity) }}" class="font-semibold text-slate-900 hover:text-violet-600 dark:text-white dark:hover:text-violet-400">{{ $opportunity->title }}</a>
                                </div>
                                <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $opportunity->customer?->name }}</div>
                                <div class="mt-2 flex items-center justify-between">
                                    <span class="text-sm font-semibold text-violet-600 dark:text-violet-400">{{ number_format((float) $opportunity->amount, 0) }} ₪</span>
                                    <span class="text-xs text-slate-500 dark:text-slate-400">{{ $opportunity->probability }}%</span>
                                </div>
                                <div class="mt-1 text-xs text-slate-400 dark:text-slate-500">{{ $opportunity->assignedTo?->name }}</div>
                            </div>
                        @empty
                            <div class="mx-2 rounded-lg border-2 border-dashed border-slate-300 py-6 text-center text-xs text-slate-400 dark:border-slate-700">لا فرص في هذه المرحلة</div>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>