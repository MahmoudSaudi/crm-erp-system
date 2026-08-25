<aside
    class="fixed inset-y-0 right-0 z-40 w-72 bg-slate-900 text-slate-300 transform transition-transform duration-200"
    x-bind:class="!visible ? 'translate-x-full' : 'translate-x-0'"
>
    <div class="flex flex-col h-full">
        {{-- Brand --}}
        <div class="h-16 flex items-center justify-between px-5 border-b border-slate-800">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
                <x-application-logo class="w-8 h-8 shrink-0" />
                <div class="text-white font-bold leading-tight text-lg">Nexus</div>
            </a>
            <button type="button" class="lg:hidden text-slate-400 hover:text-white" @click="sidebarOpen = false" aria-label="إغلاق القائمة">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        {{-- Nav --}}
        <nav class="flex-1 overflow-y-auto scrollbar-hide px-4 py-4 space-y-6 text-sm">
            {{-- Home --}}
            @can('permission.view_dashboard')
                <div class="space-y-1">
                    <x-layout.nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" icon="dashboard">
                        لوحة التحكم
                    </x-layout.nav-link>
                </div>
            @endcan

            {{-- CRM --}}
            @canany(['permission.view_leads', 'permission.view_customers', 'permission.view_opportunities', 'permission.view_activities'])
                <div>
                    <div class="px-3 mb-2 text-[11px] font-semibold text-slate-500 uppercase tracking-wide">إدارة العملاء CRM</div>
                    <div class="space-y-1">
                        @can('permission.view_leads')
                            <x-layout.nav-link :href="route('leads.index')" :active="request()->routeIs('leads.*')" icon="leads">
                                العملاء المحتملون
                            </x-layout.nav-link>
                        @endcan
                        @can('permission.view_customers')
                            <x-layout.nav-link :href="route('customers.index')" :active="request()->routeIs('customers.*')" icon="customers">
                                العملاء
                            </x-layout.nav-link>
                        @endcan
                        @can('permission.view_opportunities')
                            <x-layout.nav-link :href="route('opportunities.index')" :active="request()->routeIs('opportunities.*')" icon="opportunities">
                                الفرص
                            </x-layout.nav-link>
                        @endcan
                        @can('permission.view_activities')
                            <x-layout.nav-link :href="route('activities.index')" :active="request()->routeIs('activities.*')" icon="activities">
                                الأنشطة
                            </x-layout.nav-link>
                        @endcan
                    </div>
                </div>
            @endcanany

            {{-- Sales --}}
            @canany(['permission.view_sales_orders', 'permission.view_invoices', 'permission.view_payments', 'permission.view_expenses'])
                <div>
                    <div class="px-3 mb-2 text-[11px] font-semibold text-slate-500 uppercase tracking-wide">المبيعات والمالية</div>
                    <div class="space-y-1">
                        @can('permission.view_sales_orders')
                            <x-layout.nav-link :href="route('sales-orders.index')" :active="request()->routeIs('sales-orders.*')" icon="sales">
                                أوامر البيع
                            </x-layout.nav-link>
                        @endcan
                        @can('permission.view_invoices')
                            <x-layout.nav-link :href="route('invoices.index')" :active="request()->routeIs('invoices.*')" icon="invoices">
                                الفواتير
                            </x-layout.nav-link>
                        @endcan
                        @can('permission.view_payments')
                            <x-layout.nav-link :href="route('payments.index')" :active="request()->routeIs('payments.*')" icon="payments">
                                المدفوعات
                            </x-layout.nav-link>
                        @endcan
                        @can('permission.view_expenses')
                            <x-layout.nav-link :href="route('expenses.index')" :active="request()->routeIs('expenses.*')" icon="expenses">
                                المصروفات
                            </x-layout.nav-link>
                        @endcan
                    </div>
                </div>
            @endcanany

            {{-- Purchasing --}}
            @canany(['permission.view_suppliers', 'permission.view_purchase_orders'])
                <div>
                    <div class="px-3 mb-2 text-[11px] font-semibold text-slate-500 uppercase tracking-wide">المشتريات</div>
                    <div class="space-y-1">
                        @can('permission.view_suppliers')
                            <x-layout.nav-link :href="route('suppliers.index')" :active="request()->routeIs('suppliers.*')" icon="suppliers">
                                الموردون
                            </x-layout.nav-link>
                        @endcan
                        @can('permission.view_purchase_orders')
                            <x-layout.nav-link :href="route('purchase-orders.index')" :active="request()->routeIs('purchase-orders.*')" icon="purchase">
                                أوامر الشراء
                            </x-layout.nav-link>
                        @endcan
                    </div>
                </div>
            @endcanany

            {{-- Inventory --}}
            @canany(['permission.view_products', 'permission.view_stock', 'permission.view_warehouses', 'permission.manage_categories', 'permission.manage_units'])
                <div>
                    <div class="px-3 mb-2 text-[11px] font-semibold text-slate-500 uppercase tracking-wide">المنتجات والمخزون</div>
                    <div class="space-y-1">
                        @can('permission.view_products')
                            <x-layout.nav-link :href="route('products.index')" :active="request()->routeIs('products.*')" icon="products">
                                المنتجات
                            </x-layout.nav-link>
                        @endcan
                        @can('permission.manage_categories')
                            <x-layout.nav-link :href="route('categories.index')" :active="request()->routeIs('categories.*')" icon="categories">
                                التصنيفات
                            </x-layout.nav-link>
                        @endcan
                        @can('permission.view_stock')
                            <x-layout.nav-link :href="route('stock.index')" :active="request()->routeIs('stock.*')" icon="stock">
                                المخزون
                            </x-layout.nav-link>
                        @endcan
                    </div>
                </div>
            @endcanany

            {{-- Support --}}
            @can('permission.view_tickets')
                <div>
                    <div class="px-3 mb-2 text-[11px] font-semibold text-slate-500 uppercase tracking-wide">الدعم</div>
                    <div class="space-y-1">
                        <x-layout.nav-link :href="route('tickets.index')" :active="request()->routeIs('tickets.*')" icon="tickets">
                            تذاكر الدعم
                        </x-layout.nav-link>
                    </div>
                </div>
            @endcan

            {{-- HR --}}
            @canany(['permission.view_employees', 'permission.view_payroll', 'permission.view_attendance', 'permission.manage_departments'])
                <div>
                    <div class="px-3 mb-2 text-[11px] font-semibold text-slate-500 uppercase tracking-wide">الموارد البشرية</div>
                    <div class="space-y-1">
                        @can('permission.manage_departments')
                            <x-layout.nav-link :href="route('departments.index')" :active="request()->routeIs('departments.*')" icon="categories">
                                الأقسام
                            </x-layout.nav-link>
                        @endcan
                        @can('permission.view_employees')
                            <x-layout.nav-link :href="route('employees.index')" :active="request()->routeIs('employees.*')" icon="employees">
                                الموظفون
                            </x-layout.nav-link>
                        @endcan
                        @can('permission.view_payroll')
                            <x-layout.nav-link :href="route('payroll.index')" :active="request()->routeIs('payroll.*')" icon="payroll">
                                الرواتب
                            </x-layout.nav-link>
                        @endcan
                        @can('permission.view_attendance')
                            <x-layout.nav-link :href="route('attendance.index')" :active="request()->routeIs('attendance.*')" icon="attendance">
                                الحضور
                            </x-layout.nav-link>
                        @endcan
                    </div>
                </div>
            @endcanany

            {{-- Reports --}}
            @can('permission.view_reports')
                <div>
                    <div class="px-3 mb-2 text-[11px] font-semibold text-slate-500 uppercase tracking-wide">التقارير</div>
                    <div class="space-y-1">
                        <x-layout.nav-link :href="route('reports.index')" :active="request()->routeIs('reports.*')" icon="reports">
                            التقارير
                        </x-layout.nav-link>
                    </div>
                </div>
            @endcan

            {{-- Admin --}}
            @canany(['permission.view_users', 'permission.manage_roles', 'permission.view_audit_logs'])
                <div>
                    <div class="px-3 mb-2 text-[11px] font-semibold text-slate-500 uppercase tracking-wide">الإدارة</div>
                    <div class="space-y-1">
                        @can('permission.view_users')
                            <x-layout.nav-link :href="route('users.index')" :active="request()->routeIs('users.*')" icon="users">
                                المستخدمون
                            </x-layout.nav-link>
                        @endcan
                        @can('permission.manage_roles')
                            <x-layout.nav-link :href="route('roles.index')" :active="request()->routeIs('roles.*')" icon="roles">
                                الأدوار والصلاحيات
                            </x-layout.nav-link>
                        @endcan
                        @can('permission.view_audit_logs')
                            <x-layout.nav-link :href="route('audit-logs.index')" :active="request()->routeIs('audit-logs.*')" icon="audit">
                                سجل التدقيق
                            </x-layout.nav-link>
                        @endcan
                    </div>
                </div>
            @endcanany
        </nav>

        <div class="mt-auto px-4 py-4 space-y-1 border-t border-slate-800">
            <a href="{{ route('profile.edit') }}" class="{{ request()->routeIs('profile.edit') ? 'flex items-center gap-3 px-3 py-2.5 rounded-lg bg-indigo-600 text-white font-medium' : 'flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white transition' }}">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
                <span>الإعدادات</span>
            </a>
            
            <form method="POST" action="{{ route('logout') }}" class="w-full">
                @csrf
                <button type="submit" class="w-full flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-lg text-rose-500 hover:text-rose-400 hover:bg-slate-800 transition">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    <span>تسجيل الخروج</span>
                </button>
            </form>
        </div>
    </div>
</aside>