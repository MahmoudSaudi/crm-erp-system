<x-app-layout>
    <x-slot name="title">{{ $ticket->ticket_number }} — {{ $ticket->subject }}</x-slot>

    <div class="mx-auto max-w-4xl space-y-6">
        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <h2 class="text-2xl font-bold text-slate-900 dark:text-white"><span class="font-mono" dir="ltr">{{ $ticket->ticket_number }}</span></h2>
                    @php
                        $ticketStatus = \App\Enums\TicketStatus::from($ticket->status);
                        $ticketPriority = \App\Enums\TicketPriority::from($ticket->priority);
                    @endphp
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $ticketStatus?->color() }}">{{ $ticketStatus?->label() }}</span>
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $ticketPriority?->color() }}">{{ $ticketPriority?->label() }}</span>
                </div>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $ticket->category ?? 'بدون تصنيف' }} · أنشأها {{ $ticket->creator?->name ?? '—' }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if ($ticket->status !== 'closed')
                    @can('permission.resolve_tickets')
                        <form method="POST" action="{{ route('tickets.status', $ticket) }}" onsubmit="return confirm('تحديد التذكرة كمحلولة؟')">
                            @csrf
                            <input type="hidden" name="status" value="resolved" />
                            <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                حلّ التذكرة
                            </button>
                        </form>
                    @endcan
                @endif
                @if (in_array($ticket->status, ['resolved', 'closed'], true))
                    @can('permission.reply_tickets')
                        <form method="POST" action="{{ route('tickets.status', $ticket) }}" onsubmit="return confirm('إعادة فتح التذكرة؟')">
                            @csrf
                            <input type="hidden" name="status" value="open" />
                            <button type="submit" class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-800">إعادة فتح</button>
                        </form>
                    @endcan
                @endif
                @can('permission.resolve_tickets')
                    @if ($ticket->status !== 'closed')
                        <form method="POST" action="{{ route('tickets.status', $ticket) }}" onsubmit="return confirm('غلق التذكرة؟')">
                            @csrf
                            <input type="hidden" name="status" value="closed" />
                            <button type="submit" class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-800">غلق</button>
                        </form>
                    @endif
                @endcan
                @can('permission.delete_tickets')
                    @if ($ticket->status !== 'closed')
                        <form method="POST" action="{{ route('tickets.destroy', $ticket) }}" onsubmit="return confirm('حذف التذكرة؟')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="inline-flex items-center rounded-lg border border-rose-200 px-4 py-2 text-sm font-medium text-rose-600 hover:bg-rose-50 dark:border-rose-500/30 dark:hover:bg-rose-500/10">حذف</button>
                        </form>
                    @endif
                @endcan
            </div>
        </div>

        @if ($ticket->status === 'closed')
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-800/40 dark:text-slate-300">هذه التذكرة مغلقة.</div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Thread --}}
            <div class="lg:col-span-2 space-y-4">
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <div class="flex items-start gap-3">
                        <span class="flex items-center justify-center w-9 h-9 rounded-full bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ mb_substr($ticket->creator?->name ?? '?', 0, 1) }}</span>
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-semibold text-slate-900 dark:text-white">{{ $ticket->creator?->name ?? '—' }}</span>
                                <span class="text-xs text-slate-400">{{ $ticket->created_at->diffForHumans() }}</span>
                            </div>
                            <h3 class="mt-1 font-medium text-slate-900 dark:text-white">{{ $ticket->subject }}</h3>
                            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300 whitespace-pre-line">{{ $ticket->message }}</p>
                        </div>
                    </div>
                </div>

                @foreach ($ticket->messages as $message)
                    <div class="rounded-xl border p-5 shadow-sm {{ $message->is_internal ? 'border-amber-200 bg-amber-50/50 dark:border-amber-500/20 dark:bg-amber-500/5' : 'border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900' }}">
                        <div class="flex items-start gap-3">
                            <span class="flex items-center justify-center w-9 h-9 rounded-full bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ mb_substr($message->user?->name ?? '?', 0, 1) }}</span>
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-semibold text-slate-900 dark:text-white">{{ $message->user?->name }}</span>
                                    <span class="text-xs text-slate-400">{{ $message->created_at->diffForHumans() }}</span>
                                    @if ($message->is_internal)
                                        <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-medium text-amber-700 dark:bg-amber-500/20 dark:text-amber-300">ملاحظة داخلية</span>
                                    @endif
                                </div>
                                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300 whitespace-pre-line">{{ $message->body }}</p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Sidebar --}}
            <div class="space-y-4">
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">بيانات التذكرة</h3>
                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex items-center justify-between"><dt class="text-slate-500 dark:text-slate-400">العميل</dt><dd class="font-medium text-slate-900 dark:text-white">{{ $ticket->customer?->name ?? '—' }}</dd></div>
                        <div class="flex items-center justify-between"><dt class="text-slate-500 dark:text-slate-400">التصنيف</dt><dd class="font-medium text-slate-900 dark:text-white">{{ $ticket->category ?? '—' }}</dd></div>
                        @if ($ticket->resolved_at)
                            <div class="flex items-center justify-between"><dt class="text-slate-500 dark:text-slate-400">تاريخ الحل</dt><dd class="font-medium text-slate-900 dark:text-white">{{ $ticket->resolved_at->format('Y-m-d H:i') }}</dd></div>
                        @endif
                    </dl>
                </div>

                @can('permission.assign_tickets')
                    <form method="POST" action="{{ route('tickets.assign', $ticket) }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                        @csrf
                        <x-input-label for="assigned_to" value="تعيين إلى" />
                        <select id="assigned_to" name="assigned_to" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                            <option value="">غير معيّنة</option>
                            @foreach ($agents as $agent)
                                <option value="{{ $agent['id'] }}" @selected($ticket->assigned_to == $agent['id'])>{{ $agent['name'] }}</option>
                            @endforeach
                        </select>
                        <x-primary-button class="mt-3">{{ __('حفظ التعيين') }}</x-primary-button>
                    </form>
                @endcan
            </div>
        </div>

        {{-- Reply --}}
        @if ($ticket->status !== 'closed')
            @can('permission.reply_tickets')
                <form method="POST" action="{{ route('tickets.reply', $ticket) }}" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    @csrf
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">الرد</h3>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                            <input type="checkbox" name="is_internal" value="1" class="rounded border-slate-300 text-amber-600 shadow-sm focus:ring-amber-500 dark:border-slate-600 dark:bg-slate-800" />
                            ملاحظة داخلية (لا تُرى للعميل)
                        </label>
                    </div>
                    <textarea name="body" rows="3" class="mt-4 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200" placeholder="اكتب ردًا أو ملاحظة..." required></textarea>
                    <x-input-error :messages="$errors->get('body')" class="mt-2" />
                    <div class="mt-4">
                        <x-primary-button>{{ __('إرسال') }}</x-primary-button>
                    </div>
                </form>
            @endcan
        @endif
    </div>
</x-app-layout>