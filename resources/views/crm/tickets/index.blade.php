<x-app-layout>
    <x-slot name="title">{{ __('تذاكر الدعم') }}</x-slot>

    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('تذاكر الدعم') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">متابعة طلبات الدعم وحلّها</p>
            </div>
            @can('permission.create_tickets')
                <a href="{{ route('tickets.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                    {{ __('إنشاء تذكرة') }}
                </a>
            @endcan
        </div>

        <form method="GET" action="{{ route('tickets.index') }}" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                <div>
                    <x-input-label for="search" value="بحث" />
                    <x-text-input id="search" name="search" type="text" class="mt-1 block w-full" value="{{ request('search') }}" placeholder="الرقم أو الموضوع أو العميل..." />
                </div>
                <div>
                    <x-input-label for="status" value="الحالة" />
                    <select id="status" name="status" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                        <option value="">كل الحالات</option>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected($activeStatus === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="priority" value="الأولوية" />
                    <select id="priority" name="priority" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                        <option value="">كل الأولويات</option>
                        @foreach ($priorities as $value => $label)
                            <option value="{{ $value }}" @selected($activePriority === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600">تصفية</button>
                    @if (request()->hasAny(['search', 'status', 'priority']))
                        <a href="{{ route('tickets.index') }}" class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-800">مسح</a>
                    @endif
                </div>
            </div>
        </form>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-800/60">
                        <tr>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">الرقم</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">الموضوع</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">العميل</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">الأولوية</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">الحالة</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">المسؤول</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($tickets as $ticket)
                            @php
                                $ticketStatus = \App\Enums\TicketStatus::from($ticket->status);
                                $ticketPriority = \App\Enums\TicketPriority::from($ticket->priority);
                            @endphp
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td class="px-4 py-3">
                                    <a href="{{ route('tickets.show', $ticket) }}" class="font-medium text-indigo-600 hover:text-indigo-700 dark:text-indigo-400" dir="ltr">{{ $ticket->ticket_number }}</a>
                                </td>
                                <td class="px-4 py-3 font-medium text-slate-900 dark:text-white">{{ $ticket->subject }}</td>
                                <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">{{ $ticket->customer?->name ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $ticketPriority?->color() }}">{{ $ticketPriority?->label() }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $ticketStatus?->color() }}">{{ $ticketStatus?->label() }}</span>
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-300">{{ $ticket->assignee?->name ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('tickets.show', $ticket) }}" class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-300" title="عرض">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0zm-9 0a9 9 0 0118 0 9 9 0 01-18 0z" /></svg>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <x-empty-state icon="inbox" title="{{ __('لا توجد تذاكر بعد') }}" :description="__('لم يتم فتح أي تذاكر دعم بعد. ستظهر هنا استفسارات وتعطلات العملاء.')" />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($tickets->hasPages())
                <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-700">{{ $tickets->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>