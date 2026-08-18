<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RbacSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = $this->permissions();

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                ['slug' => $permission['slug']],
                ['name' => $permission['name'], 'group' => $permission['group']]
            );
        }

        $roles = $this->roles();

        foreach ($roles as $role) {
            $roleModel = Role::updateOrCreate(
                ['slug' => $role['slug']],
                [
                    'name' => $role['name'],
                    'description' => $role['description'] ?? null,
                    'is_system' => true,
                ]
            );

            $rolePermissions = collect($permissions)
                ->filter(fn ($permission) => in_array($permission['slug'], $role['permissions'], true))
                ->pluck('slug')
                ->all();

            $roleModel->permissions()->sync(Permission::whereIn('slug', $rolePermissions)->pluck('id'));
        }

        $this->createDemoUsers();
    }

    private function createDemoUsers(): void
    {
        $users = [
            ['name' => 'أحمد المدير', 'email' => 'admin@crm.test', 'role' => 'admin', 'password' => 'password'],
            ['name' => 'محمد مدير المبيعات', 'email' => 'manager@crm.test', 'role' => 'sales-manager', 'password' => 'password'],
            ['name' => 'سارة مندوبة المبيعات', 'email' => 'rep@crm.test', 'role' => 'sales-rep', 'password' => 'password'],
            ['name' => 'خالد مهندس الدعم', 'email' => 'support@crm.test', 'role' => 'support-agent', 'password' => 'password'],
            ['name' => 'منى المحاسبة', 'email' => 'accountant@crm.test', 'role' => 'accountant', 'password' => 'password'],
            ['name' => 'محمود مأمور المخزن', 'email' => 'warehouse@crm.test', 'role' => 'warehouse-manager', 'password' => 'password'],
            ['name' => 'هشام المشتريات', 'email' => 'purchasing@crm.test', 'role' => 'purchasing-officer', 'password' => 'password'],
            ['name' => 'إيمان شؤون الموظفين', 'email' => 'hr@crm.test', 'role' => 'hr-admin', 'password' => 'password'],
        ];

        foreach ($users as $user) {
            $role = Role::where('slug', $user['role'])->first();

            if (! $role) {
                continue;
            }

            User::updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'role_id' => $role->id,
                    'is_active' => true,
                    'password' => Hash::make($user['password']),
                    'email_verified_at' => now(),
                ]
            );
        }
    }

    private function roles(): array
    {
        return [
            [
                'slug' => 'admin',
                'name' => 'مدير النظام',
                'description' => 'صلاحيات كاملة على كل النظام',
                'permissions' => Permission::all()->pluck('slug')->all(),
            ],
            [
                'slug' => 'sales-manager',
                'name' => 'مدير المبيعات',
                'description' => 'إدارة المبيعات وفرق العملاء',
                'permissions' => $this->group([
                    'dashboard' => ['view_dashboard'],
                    'leads' => ['view_leads', 'create_leads', 'edit_leads', 'delete_leads', 'convert_leads'],
                    'customers' => ['view_customers', 'create_customers', 'edit_customers', 'delete_customers'],
                    'opportunities' => ['view_opportunities', 'create_opportunities', 'edit_opportunities', 'delete_opportunities', 'change_opportunity_stage'],
                    'activities' => ['view_activities', 'create_activities', 'edit_activities', 'delete_activities', 'complete_activities'],
                    'products' => ['view_products'],
                    'sales_orders' => ['view_sales_orders', 'create_sales_orders', 'confirm_sales_orders', 'fulfill_sales_orders', 'cancel_sales_orders'],
                    'reports' => ['view_reports'],
                ]),
            ],
            [
                'slug' => 'sales-rep',
                'name' => 'مندوب مبيعات',
                'description' => 'إدارة العملاء المحتملين والطلبات',
                'permissions' => $this->group([
                    'dashboard' => ['view_dashboard'],
                    'leads' => ['view_leads', 'create_leads', 'edit_leads', 'convert_leads'],
                    'customers' => ['view_customers', 'create_customers'],
                    'opportunities' => ['view_opportunities', 'create_opportunities', 'edit_opportunities', 'change_opportunity_stage'],
                    'activities' => ['view_activities', 'create_activities', 'edit_activities', 'delete_activities', 'complete_activities'],
                    'products' => ['view_products'],
                    'sales_orders' => ['view_sales_orders', 'create_sales_orders'],
                ]),
            ],
            [
                'slug' => 'support-agent',
                'name' => 'أخصائي دعم',
                'description' => 'إدارة التذاكر ودعم العملاء',
                'permissions' => $this->group([
                    'dashboard' => ['view_dashboard'],
                    'tickets' => ['view_tickets', 'create_tickets', 'reply_tickets', 'resolve_tickets', 'assign_tickets', 'delete_tickets'],
                    'customers' => ['view_customers'],
                    'activities' => ['view_activities', 'create_activities', 'edit_activities', 'delete_activities', 'complete_activities'],
                ]),
            ],
            [
                'slug' => 'accountant',
                'name' => 'محاسب',
                'description' => 'إدارة الفواتير والمدفوعات والمصروفات',
                'permissions' => $this->group([
                    'dashboard' => ['view_dashboard'],
                    'invoices' => ['view_invoices', 'create_invoices', 'edit_invoices', 'print_invoices', 'send_invoices'],
                    'payments' => ['view_payments', 'create_payments'],
                    'expenses' => ['view_expenses', 'create_expenses', 'edit_expenses', 'delete_expenses'],
                    'customers' => ['view_customers'],
                    'sales_orders' => ['view_sales_orders'],
                    'reports' => ['view_reports'],
                ]),
            ],
            [
                'slug' => 'warehouse-manager',
                'name' => 'مأمور مخزن',
                'description' => 'إدارة المنتجات والمخزون والاستلام',
                'permissions' => $this->group([
                    'dashboard' => ['view_dashboard'],
                    'products' => ['view_products', 'create_products', 'edit_products', 'delete_products'],
                    'categories' => ['manage_categories'],
                    'units' => ['manage_units'],
                    'warehouses' => ['view_warehouses', 'manage_warehouses'],
                    'stock' => ['view_stock', 'adjust_stock'],
                    'suppliers' => ['view_suppliers'],
                    'purchase_orders' => ['view_purchase_orders', 'receive_purchase_orders'],
                ]),
            ],
            [
                'slug' => 'purchasing-officer',
                'name' => 'مسؤول مشتريات',
                'description' => 'إدارة الموردين وأوامر الشراء',
                'permissions' => $this->group([
                    'dashboard' => ['view_dashboard'],
                    'suppliers' => ['view_suppliers', 'create_suppliers', 'edit_suppliers', 'delete_suppliers'],
                    'purchase_orders' => ['view_purchase_orders', 'create_purchase_orders', 'confirm_purchase_orders', 'receive_purchase_orders', 'cancel_purchase_orders'],
                    'products' => ['view_products'],
                    'stock' => ['view_stock'],
                    'reports' => ['view_reports'],
                ]),
            ],
            [
                'slug' => 'hr-admin',
                'name' => 'أخصائي موارد بشرية',
                'description' => 'إدارة الموظفين والرواتب والحضور',
                'permissions' => $this->group([
                    'dashboard' => ['view_dashboard'],
                    'departments' => ['manage_departments'],
                    'employees' => ['view_employees', 'create_employees', 'edit_employees', 'delete_employees'],
                    'payroll' => ['view_payroll', 'run_payroll', 'approve_payroll', 'pay_payroll'],
                    'attendance' => ['view_attendance', 'manage_attendance'],
                ]),
            ],
        ];
    }

    private function group(array $groups): array
    {
        return array_merge(...array_values($groups));
    }

    private function permissions(): array
    {
        return [
            ['name' => 'عرض لوحة التحكم', 'slug' => 'view_dashboard', 'group' => 'dashboard'],

            ['name' => 'عرض العملاء المحتملين', 'slug' => 'view_leads', 'group' => 'leads'],
            ['name' => 'إضافة عميل محتمل', 'slug' => 'create_leads', 'group' => 'leads'],
            ['name' => 'تعديل عميل محتمل', 'slug' => 'edit_leads', 'group' => 'leads'],
            ['name' => 'حذف عميل محتمل', 'slug' => 'delete_leads', 'group' => 'leads'],
            ['name' => 'تحويل عميل محتمل', 'slug' => 'convert_leads', 'group' => 'leads'],

            ['name' => 'عرض العملاء', 'slug' => 'view_customers', 'group' => 'customers'],
            ['name' => 'إضافة عميل', 'slug' => 'create_customers', 'group' => 'customers'],
            ['name' => 'تعديل عميل', 'slug' => 'edit_customers', 'group' => 'customers'],
            ['name' => 'حذف عميل', 'slug' => 'delete_customers', 'group' => 'customers'],

            ['name' => 'عرض الفرص', 'slug' => 'view_opportunities', 'group' => 'opportunities'],
            ['name' => 'إضافة فرصة', 'slug' => 'create_opportunities', 'group' => 'opportunities'],
            ['name' => 'تعديل فرصة', 'slug' => 'edit_opportunities', 'group' => 'opportunities'],
            ['name' => 'حذف فرصة', 'slug' => 'delete_opportunities', 'group' => 'opportunities'],
            ['name' => 'تغيير مرحلة الفرصة', 'slug' => 'change_opportunity_stage', 'group' => 'opportunities'],

            ['name' => 'عرض الأنشطة', 'slug' => 'view_activities', 'group' => 'activities'],
            ['name' => 'إضافة نشاط', 'slug' => 'create_activities', 'group' => 'activities'],
            ['name' => 'تعديل نشاط', 'slug' => 'edit_activities', 'group' => 'activities'],
            ['name' => 'حذف نشاط', 'slug' => 'delete_activities', 'group' => 'activities'],
            ['name' => 'إكمال نشاط', 'slug' => 'complete_activities', 'group' => 'activities'],

            ['name' => 'عرض التذاكر', 'slug' => 'view_tickets', 'group' => 'tickets'],
            ['name' => 'إنشاء تذكرة', 'slug' => 'create_tickets', 'group' => 'tickets'],
            ['name' => 'الرد على تذكرة', 'slug' => 'reply_tickets', 'group' => 'tickets'],
            ['name' => 'غلق تذكرة', 'slug' => 'resolve_tickets', 'group' => 'tickets'],
            ['name' => 'تعيين تذكرة', 'slug' => 'assign_tickets', 'group' => 'tickets'],
            ['name' => 'حذف تذكرة', 'slug' => 'delete_tickets', 'group' => 'tickets'],

            ['name' => 'عرض المنتجات', 'slug' => 'view_products', 'group' => 'products'],
            ['name' => 'إضافة منتج', 'slug' => 'create_products', 'group' => 'products'],
            ['name' => 'تعديل منتج', 'slug' => 'edit_products', 'group' => 'products'],
            ['name' => 'حذف منتج', 'slug' => 'delete_products', 'group' => 'products'],
            ['name' => 'إدارة التصنيفات', 'slug' => 'manage_categories', 'group' => 'categories'],
            ['name' => 'إدارة الوحدات', 'slug' => 'manage_units', 'group' => 'units'],

            ['name' => 'عرض المخازن', 'slug' => 'view_warehouses', 'group' => 'warehouses'],
            ['name' => 'إدارة المخازن', 'slug' => 'manage_warehouses', 'group' => 'warehouses'],
            ['name' => 'عرض المخزون', 'slug' => 'view_stock', 'group' => 'stock'],
            ['name' => 'ضبط المخزون', 'slug' => 'adjust_stock', 'group' => 'stock'],

            ['name' => 'عرض الموردين', 'slug' => 'view_suppliers', 'group' => 'suppliers'],
            ['name' => 'إضافة مورد', 'slug' => 'create_suppliers', 'group' => 'suppliers'],
            ['name' => 'تعديل مورد', 'slug' => 'edit_suppliers', 'group' => 'suppliers'],
            ['name' => 'حذف مورد', 'slug' => 'delete_suppliers', 'group' => 'suppliers'],

            ['name' => 'عرض أوامر الشراء', 'slug' => 'view_purchase_orders', 'group' => 'purchase_orders'],
            ['name' => 'إنشاء أمر شراء', 'slug' => 'create_purchase_orders', 'group' => 'purchase_orders'],
            ['name' => 'تأكيد أمر شراء', 'slug' => 'confirm_purchase_orders', 'group' => 'purchase_orders'],
            ['name' => 'استلام أمر شراء', 'slug' => 'receive_purchase_orders', 'group' => 'purchase_orders'],
            ['name' => 'إلغاء أمر شراء', 'slug' => 'cancel_purchase_orders', 'group' => 'purchase_orders'],

            ['name' => 'عرض أوامر البيع', 'slug' => 'view_sales_orders', 'group' => 'sales_orders'],
            ['name' => 'إنشاء أمر بيع', 'slug' => 'create_sales_orders', 'group' => 'sales_orders'],
            ['name' => 'تأكيد أمر بيع', 'slug' => 'confirm_sales_orders', 'group' => 'sales_orders'],
            ['name' => 'تنفيذ أمر بيع', 'slug' => 'fulfill_sales_orders', 'group' => 'sales_orders'],
            ['name' => 'إلغاء أمر بيع', 'slug' => 'cancel_sales_orders', 'group' => 'sales_orders'],

            ['name' => 'عرض الفواتير', 'slug' => 'view_invoices', 'group' => 'invoices'],
            ['name' => 'إنشاء فاتورة', 'slug' => 'create_invoices', 'group' => 'invoices'],
            ['name' => 'تعديل فاتورة', 'slug' => 'edit_invoices', 'group' => 'invoices'],
            ['name' => 'طباعة فاتورة', 'slug' => 'print_invoices', 'group' => 'invoices'],
            ['name' => 'إرسال فاتورة', 'slug' => 'send_invoices', 'group' => 'invoices'],

            ['name' => 'عرض المدفوعات', 'slug' => 'view_payments', 'group' => 'payments'],
            ['name' => 'تسجيل مدفوعات', 'slug' => 'create_payments', 'group' => 'payments'],

            ['name' => 'عرض المصروفات', 'slug' => 'view_expenses', 'group' => 'expenses'],
            ['name' => 'إضافة مصروف', 'slug' => 'create_expenses', 'group' => 'expenses'],
            ['name' => 'تعديل مصروف', 'slug' => 'edit_expenses', 'group' => 'expenses'],
            ['name' => 'حذف مصروف', 'slug' => 'delete_expenses', 'group' => 'expenses'],

            ['name' => 'إدارة الأقسام', 'slug' => 'manage_departments', 'group' => 'departments'],
            ['name' => 'عرض الموظفين', 'slug' => 'view_employees', 'group' => 'employees'],
            ['name' => 'إضافة موظف', 'slug' => 'create_employees', 'group' => 'employees'],
            ['name' => 'تعديل موظف', 'slug' => 'edit_employees', 'group' => 'employees'],
            ['name' => 'حذف موظف', 'slug' => 'delete_employees', 'group' => 'employees'],

            ['name' => 'عرض الرواتب', 'slug' => 'view_payroll', 'group' => 'payroll'],
            ['name' => 'تشغيل الرواتب', 'slug' => 'run_payroll', 'group' => 'payroll'],
            ['name' => 'اعتماد الرواتب', 'slug' => 'approve_payroll', 'group' => 'payroll'],
            ['name' => 'صرف الرواتب', 'slug' => 'pay_payroll', 'group' => 'payroll'],

            ['name' => 'عرض الحضور', 'slug' => 'view_attendance', 'group' => 'attendance'],
            ['name' => 'إدارة الحضور', 'slug' => 'manage_attendance', 'group' => 'attendance'],

            ['name' => 'عرض التقارير', 'slug' => 'view_reports', 'group' => 'reports'],

            ['name' => 'عرض المستخدمين', 'slug' => 'view_users', 'group' => 'users'],
            ['name' => 'إضافة مستخدم', 'slug' => 'create_users', 'group' => 'users'],
            ['name' => 'تعديل مستخدم', 'slug' => 'edit_users', 'group' => 'users'],
            ['name' => 'حذف مستخدم', 'slug' => 'delete_users', 'group' => 'users'],
            ['name' => 'إدارة الأدوار', 'slug' => 'manage_roles', 'group' => 'roles'],
            ['name' => 'عرض سجل التدقيق', 'slug' => 'view_audit_logs', 'group' => 'audit_logs'],
        ];
    }
}
