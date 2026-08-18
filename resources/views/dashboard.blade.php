<x-app-layout>
    <div class="space-y-6">
        {{-- Welcome --}}
        <div class="bg-gradient-to-l from-indigo-600 to-violet-600 rounded-2xl p-6 sm:p-8 text-white shadow-lg">
            <h2 class="text-2xl font-bold">أهلاً، {{ auth()->user()->name }} 👋</h2>
            <p class="mt-2 text-indigo-100 max-w-lg">
                مرحبًا بك في {{ config('app.name') }} — نظام متكامل لإدارة علاقات العملاء والموارد المؤسسية.
                {{ auth()->user()->role?->name }}
            </p>
        </div>

        {{-- KPI Cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <x-dashboard.stat-card label="العملاء المحتملون" :value="$stats['leads']" icon="leads" color="indigo" />
            <x-dashboard.stat-card label="العملاء" :value="$stats['customers']" icon="customers" color="emerald" />
            <a href="{{ route('products.index') }}" class="block">
                <x-dashboard.stat-card label="المنتجات" :value="$stats['products']" icon="products" color="amber" />
            </a>
            <x-dashboard.stat-card label="تذاكر الدعم" :value="$stats['tickets']" icon="tickets" color="rose" />
        </div>

        {{-- Financial KPI --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <a href="{{ route('invoices.index') }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-indigo-300 dark:border-slate-700 dark:bg-slate-900 dark:hover:border-indigo-500">
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">إجمالي المبيعات</p>
                <p class="mt-1 text-2xl font-bold text-indigo-600 dark:text-indigo-400">{{ number_format((float) $financial['sales'], 2) }} ₪</p>
            </a>
            <a href="{{ route('payments.index') }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-emerald-300 dark:border-slate-700 dark:bg-slate-900 dark:hover:border-emerald-500">
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">المُحصّل</p>
                <p class="mt-1 text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ number_format((float) $financial['collected'], 2) }} ₪</p>
            </a>
            <a href="{{ route('invoices.index') }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-rose-300 dark:border-slate-700 dark:bg-slate-900 dark:hover:border-rose-500">
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">المستحق</p>
                <p class="mt-1 text-2xl font-bold text-rose-600 dark:text-rose-400">{{ number_format((float) $financial['due'], 2) }} ₪</p>
            </a>
            <a href="{{ route('expenses.index') }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-amber-300 dark:border-slate-700 dark:bg-slate-900 dark:hover:border-amber-500">
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">المصروفات</p>
                <p class="mt-1 text-2xl font-bold text-amber-600 dark:text-amber-400">{{ number_format((float) $financial['expenses'], 2) }} ₪</p>
            </a>
        </div>

        {{-- Monthly sales chart --}}
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white">أداء المبيعات شهريًا</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">الفواتير والمدفوعات والمصروفات خلال الأشهر الستة الأخيرة</p>
                </div>
                <div class="flex items-center gap-4 text-xs font-medium text-slate-500 dark:text-slate-400">
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-indigo-500"></span> المبيعات</span>
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span> المدفوعات</span>
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span> المصروفات</span>
                </div>
            </div>
            <div class="mt-4 h-72">
                <canvas id="monthlySalesChart"></canvas>
            </div>
        </div>

        {{-- Sales pipeline summary --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white">قناة المبيعات</h3>
                    <a href="{{ route('opportunities.pipeline') }}" class="text-sm font-medium text-violet-600 hover:text-violet-700 dark:text-violet-400">عرض الكانبان ←</a>
                </div>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">قيمة الفرص المفتوحة: {{ number_format((float) $openPipelineValue, 0) }} ₪</p>
                <div class="mt-5 grid grid-cols-2 sm:grid-cols-3 gap-4">
                    <div class="rounded-lg bg-slate-50 p-4 text-center dark:bg-slate-800/60">
                        <div class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">{{ number_format((float) $wonPipelineValue, 0) }}</div>
                        <div class="mt-1 text-xs font-medium text-slate-500 dark:text-slate-400">₪ فرص رابحة</div>
                    </div>
                    @foreach ($leadStats as $stat)
                        <div class="rounded-lg bg-slate-50 p-4 text-center dark:bg-slate-800/60">
                            <div class="text-2xl font-bold text-slate-900 dark:text-white">{{ $stat['count'] }}</div>
                            <div class="mt-1 text-xs font-medium text-slate-500 dark:text-slate-400">{{ $stat['status']->label() }}</div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Recent activities --}}
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white">آخر الأنشطة</h3>
                    <a href="{{ route('activities.index') }}" class="text-sm font-medium text-sky-600 hover:text-sky-700 dark:text-sky-400">عرض الكل ←</a>
                </div>
                <ol class="mt-4 space-y-4">
                    @forelse ($recentActivities as $activity)
                        @php $activityType = \App\Enums\ActivityType::tryFrom($activity->type); @endphp
                        <li class="flex items-start gap-3">
                            <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-sky-100 text-sky-600 dark:bg-sky-500/20 dark:text-sky-400">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            </span>
                            <div class="min-w-0">
                                <div class="truncate text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $activity->subject }}</div>
                                <div class="text-xs text-slate-400 dark:text-slate-500">
                                    {{ $activityType?->label() ?: $activity->type }} · {{ $activity->created_at->diffForHumans() }}
                                </div>
                            </div>
                        </li>
                    @empty
                        <li class="text-sm text-slate-500 dark:text-slate-400">لا توجد أنشطة بعد</li>
                    @endforelse
                </ol>
            </div>
        </div>

        {{-- Quick links --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 dark:bg-slate-900 dark:border-slate-800">
            <h3 class="text-lg font-bold text-slate-900 mb-1 dark:text-white">بدء العمل</h3>
            <p class="text-sm text-slate-500 mb-5 dark:text-slate-400">الوحدات المتاحة لك حسب صلاحيات دورك</p>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                @can('permission.create_leads')
                    <a href="{{ route('leads.create') }}" class="flex items-center gap-3 p-4 rounded-xl border border-slate-200 hover:border-indigo-300 hover:bg-indigo-50 transition dark:border-slate-700 dark:hover:border-indigo-500 dark:hover:bg-indigo-500/10">
                        <span class="flex items-center justify-center w-10 h-10 rounded-lg bg-indigo-100 text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                        </span>
                        <div>
                            <div class="font-semibold text-slate-800 dark:text-slate-100">إضافة عميل محتمل</div>
                            <div class="text-xs text-slate-500 dark:text-slate-400">سجّل عميلاً جديدًا في قائمة الانتظار</div>
                        </div>
                    </a>
                @endcan
                @can('permission.create_customers')
                    <a href="{{ route('customers.create') }}" class="flex items-center gap-3 p-4 rounded-xl border border-slate-200 hover:border-emerald-300 hover:bg-emerald-50 transition dark:border-slate-700 dark:hover:border-emerald-500 dark:hover:bg-emerald-500/10">
                        <span class="flex items-center justify-center w-10 h-10 rounded-lg bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                        </span>
                        <div>
                            <div class="font-semibold text-slate-800 dark:text-slate-100">إضافة عميل</div>
                            <div class="text-xs text-slate-500 dark:text-slate-400">أضف عميلاً جديدًا إلى دليل العملاء</div>
                        </div>
                    </a>
                @endcan
                @can('permission.create_opportunities')
                    <a href="{{ route('opportunities.create') }}" class="flex items-center gap-3 p-4 rounded-xl border border-slate-200 hover:border-violet-300 hover:bg-violet-50 transition dark:border-slate-700 dark:hover:border-violet-500 dark:hover:bg-violet-500/10">
                        <span class="flex items-center justify-center w-10 h-10 rounded-lg bg-violet-100 text-violet-600 dark:bg-violet-500/20 dark:text-violet-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>
                        </span>
                        <div>
                            <div class="font-semibold text-slate-800 dark:text-slate-100">إضافة فرصة</div>
                            <div class="text-xs text-slate-500 dark:text-slate-400">أنشئ فرصة بيع جديدة</div>
                        </div>
                    </a>
                @endcan
                @can('permission.create_tickets')
                    <a href="{{ route('tickets.index') }}" class="flex items-center gap-3 p-4 rounded-xl border border-slate-200 hover:border-rose-300 hover:bg-rose-50 transition dark:border-slate-700 dark:hover:border-rose-500 dark:hover:bg-rose-500/10">
                        <span class="flex items-center justify-center w-10 h-10 rounded-lg bg-rose-100 text-rose-600 dark:bg-rose-500/20 dark:text-rose-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" /></svg>
                        </span>
                        <div>
                            <div class="font-semibold text-slate-800 dark:text-slate-100">تذكرة دعم جديدة</div>
                            <div class="text-xs text-slate-500 dark:text-slate-400">افتح تذكرة دعم لعميل</div>
                        </div>
                    </a>
                @endcan
            </div>
        </div>
    </div>
</x-app-layout>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof Chart === 'undefined') return;

        const ctx = document.getElementById('monthlySalesChart');
        if (!ctx) return;

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: @json($chart['labels']),
                datasets: [
                    {
                        label: 'المبيعات',
                        data: @json($chart['sales']),
                        backgroundColor: '#6366f1',
                        borderRadius: 6
                    },
                    {
                        label: 'المدفوعات',
                        data: @json($chart['payments']),
                        backgroundColor: '#10b981',
                        borderRadius: 6
                    },
                    {
                        label: 'المصروفات',
                        data: @json($chart['expenses']),
                        backgroundColor: '#f59e0b',
                        borderRadius: 6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        rtl: true,
                        callbacks: {
                            label: function (context) {
                                return context.dataset.label + ': ' + Number(context.parsed.y).toLocaleString('ar-EG') + ' ₪';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { family: 'Cairo' } }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function (value) {
                                return value >= 1000 ? (value / 1000) + 'k' : value;
                            }
                        }
                    }
                }
            }
        });
    });
</script>