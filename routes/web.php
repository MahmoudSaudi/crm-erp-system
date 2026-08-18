<?php

use App\Http\Controllers\CRM\ActivityController;
use App\Http\Controllers\CRM\CustomerController;
use App\Http\Controllers\CRM\LeadController;
use App\Http\Controllers\CRM\OpportunityController;
use App\Http\Controllers\CRM\SalesOrderController;
use App\Http\Controllers\CRM\TicketController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ERP\CategoryController;
use App\Http\Controllers\ERP\ExpenseController;
use App\Http\Controllers\ERP\InvoiceController;
use App\Http\Controllers\ERP\PaymentController;
use App\Http\Controllers\ERP\ProductController;
use App\Http\Controllers\ERP\PurchaseOrderController;
use App\Http\Controllers\ERP\StockController;
use App\Http\Controllers\ERP\SupplierController;
use App\Http\Controllers\ERP\UnitController;
use App\Http\Controllers\ERP\WarehouseController;
use App\Http\Controllers\HR\AttendanceController;
use App\Http\Controllers\HR\DepartmentController;
use App\Http\Controllers\HR\EmployeeController;
use App\Http\Controllers\HR\PayrollController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\Settings\AuditLogController;
use App\Http\Controllers\Settings\RoleController;
use App\Http\Controllers\Settings\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified', 'permission:view_dashboard'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/leads/pipeline', [LeadController::class, 'pipeline'])->name('leads.pipeline')->middleware('permission:view_leads');
    Route::get('/leads', [LeadController::class, 'index'])->name('leads.index')->middleware('permission:view_leads');
    Route::get('/leads/create', [LeadController::class, 'create'])->name('leads.create')->middleware('permission:create_leads');
    Route::post('/leads', [LeadController::class, 'store'])->name('leads.store')->middleware('permission:create_leads');
    Route::get('/leads/{lead}/edit', [LeadController::class, 'edit'])->name('leads.edit')->middleware('permission:edit_leads');
    Route::match(['put', 'patch'], '/leads/{lead}', [LeadController::class, 'update'])->name('leads.update')->middleware('permission:edit_leads');
    Route::delete('/leads/{lead}', [LeadController::class, 'destroy'])->name('leads.destroy')->middleware('permission:delete_leads');
    Route::post('/leads/{lead}/convert', [LeadController::class, 'convert'])->name('leads.convert')->middleware('permission:convert_leads');
    Route::patch('/leads/{lead}/status', [LeadController::class, 'updateStatus'])->name('leads.update-status')->middleware('permission:edit_leads');

    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index')->middleware('permission:view_customers');
    Route::get('/customers/create', [CustomerController::class, 'create'])->name('customers.create')->middleware('permission:create_customers');
    Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store')->middleware('permission:create_customers');
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show')->middleware('permission:view_customers');
    Route::get('/customers/{customer}/edit', [CustomerController::class, 'edit'])->name('customers.edit')->middleware('permission:edit_customers');
    Route::match(['put', 'patch'], '/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update')->middleware('permission:edit_customers');
    Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy')->middleware('permission:delete_customers');

    Route::get('/opportunities/pipeline', [OpportunityController::class, 'pipeline'])->name('opportunities.pipeline')->middleware('permission:view_opportunities');
    Route::get('/opportunities', [OpportunityController::class, 'index'])->name('opportunities.index')->middleware('permission:view_opportunities');
    Route::get('/opportunities/create', [OpportunityController::class, 'create'])->name('opportunities.create')->middleware('permission:create_opportunities');
    Route::post('/opportunities', [OpportunityController::class, 'store'])->name('opportunities.store')->middleware('permission:create_opportunities');
    Route::get('/opportunities/{opportunity}', [OpportunityController::class, 'show'])->name('opportunities.show')->middleware('permission:view_opportunities');
    Route::get('/opportunities/{opportunity}/edit', [OpportunityController::class, 'edit'])->name('opportunities.edit')->middleware('permission:edit_opportunities');
    Route::match(['put', 'patch'], '/opportunities/{opportunity}', [OpportunityController::class, 'update'])->name('opportunities.update')->middleware('permission:edit_opportunities');
    Route::patch('/opportunities/{opportunity}/stage', [OpportunityController::class, 'updateStage'])->name('opportunities.update-stage')->middleware('permission:change_opportunity_stage');
    Route::delete('/opportunities/{opportunity}', [OpportunityController::class, 'destroy'])->name('opportunities.destroy')->middleware('permission:delete_opportunities');

    Route::get('/activities', [ActivityController::class, 'index'])->name('activities.index')->middleware('permission:view_activities');
    Route::get('/activities/create', [ActivityController::class, 'create'])->name('activities.create')->middleware('permission:create_activities');
    Route::post('/activities', [ActivityController::class, 'store'])->name('activities.store')->middleware('permission:create_activities');
    Route::get('/activities/{activity}/edit', [ActivityController::class, 'edit'])->name('activities.edit')->middleware('permission:edit_activities');
    Route::match(['put', 'patch'], '/activities/{activity}', [ActivityController::class, 'update'])->name('activities.update')->middleware('permission:edit_activities');
    Route::delete('/activities/{activity}', [ActivityController::class, 'destroy'])->name('activities.destroy')->middleware('permission:delete_activities');
    Route::post('/activities/{activity}/complete', [ActivityController::class, 'complete'])->name('activities.complete')->middleware('permission:complete_activities');

    Route::get('/sales-orders', [SalesOrderController::class, 'index'])->name('sales-orders.index')->middleware('permission:view_sales_orders');
    Route::get('/sales-orders/create', [SalesOrderController::class, 'create'])->name('sales-orders.create')->middleware('permission:create_sales_orders');
    Route::post('/sales-orders', [SalesOrderController::class, 'store'])->name('sales-orders.store')->middleware('permission:create_sales_orders');
    Route::get('/sales-orders/{sales_order}', [SalesOrderController::class, 'show'])->name('sales-orders.show')->middleware('permission:view_sales_orders');
    Route::get('/sales-orders/{sales_order}/edit', [SalesOrderController::class, 'edit'])->name('sales-orders.edit')->middleware('permission:create_sales_orders');
    Route::match(['put', 'patch'], '/sales-orders/{sales_order}', [SalesOrderController::class, 'update'])->name('sales-orders.update')->middleware('permission:create_sales_orders');
    Route::delete('/sales-orders/{sales_order}', [SalesOrderController::class, 'destroy'])->name('sales-orders.destroy')->middleware('permission:cancel_sales_orders');
    Route::post('/sales-orders/{sales_order}/confirm', [SalesOrderController::class, 'confirm'])->name('sales-orders.confirm')->middleware('permission:confirm_sales_orders');
    Route::post('/sales-orders/{sales_order}/fulfill', [SalesOrderController::class, 'fulfill'])->name('sales-orders.fulfill')->middleware('permission:fulfill_sales_orders');
    Route::post('/sales-orders/{sales_order}/cancel', [SalesOrderController::class, 'cancel'])->name('sales-orders.cancel')->middleware('permission:cancel_sales_orders');

    Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index')->middleware('permission:view_invoices');
    Route::get('/invoices/create', [InvoiceController::class, 'create'])->name('invoices.create')->middleware('permission:create_invoices');
    Route::post('/invoices', [InvoiceController::class, 'store'])->name('invoices.store')->middleware('permission:create_invoices');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show')->middleware('permission:view_invoices');
    Route::get('/invoices/{invoice}/print', [InvoiceController::class, 'print'])->name('invoices.print')->middleware('permission:view_invoices');
    Route::post('/invoices/{invoice}/send', [InvoiceController::class, 'send'])->name('invoices.send')->middleware('permission:create_invoices');
    Route::delete('/invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy')->middleware('permission:create_invoices');

    Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index')->middleware('permission:view_payments');
    Route::get('/payments/create', [PaymentController::class, 'create'])->name('payments.create')->middleware('permission:create_payments');
    Route::post('/payments', [PaymentController::class, 'store'])->name('payments.store')->middleware('permission:create_payments');

    Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index')->middleware('permission:view_expenses');
    Route::get('/expenses/create', [ExpenseController::class, 'create'])->name('expenses.create')->middleware('permission:create_expenses');
    Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store')->middleware('permission:create_expenses');
    Route::get('/expenses/{expense}/edit', [ExpenseController::class, 'edit'])->name('expenses.edit')->middleware('permission:create_expenses');
    Route::match(['put', 'patch'], '/expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update')->middleware('permission:create_expenses');
    Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy')->middleware('permission:create_expenses');

    Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index')->middleware('permission:view_suppliers');
    Route::get('/suppliers/create', [SupplierController::class, 'create'])->name('suppliers.create')->middleware('permission:create_suppliers');
    Route::post('/suppliers', [SupplierController::class, 'store'])->name('suppliers.store')->middleware('permission:create_suppliers');
    Route::get('/suppliers/{supplier}/edit', [SupplierController::class, 'edit'])->name('suppliers.edit')->middleware('permission:edit_suppliers');
    Route::match(['put', 'patch'], '/suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update')->middleware('permission:edit_suppliers');
    Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy')->middleware('permission:delete_suppliers');

    Route::get('/purchase-orders', [PurchaseOrderController::class, 'index'])->name('purchase-orders.index')->middleware('permission:view_purchase_orders');
    Route::get('/purchase-orders/create', [PurchaseOrderController::class, 'create'])->name('purchase-orders.create')->middleware('permission:create_purchase_orders');
    Route::post('/purchase-orders', [PurchaseOrderController::class, 'store'])->name('purchase-orders.store')->middleware('permission:create_purchase_orders');
    Route::get('/purchase-orders/{purchase_order}', [PurchaseOrderController::class, 'show'])->name('purchase-orders.show')->middleware('permission:view_purchase_orders');
    Route::get('/purchase-orders/{purchase_order}/edit', [PurchaseOrderController::class, 'edit'])->name('purchase-orders.edit')->middleware('permission:create_purchase_orders');
    Route::match(['put', 'patch'], '/purchase-orders/{purchase_order}', [PurchaseOrderController::class, 'update'])->name('purchase-orders.update')->middleware('permission:create_purchase_orders');
    Route::delete('/purchase-orders/{purchase_order}', [PurchaseOrderController::class, 'destroy'])->name('purchase-orders.destroy')->middleware('permission:cancel_purchase_orders');
    Route::post('/purchase-orders/{purchase_order}/confirm', [PurchaseOrderController::class, 'confirm'])->name('purchase-orders.confirm')->middleware('permission:confirm_purchase_orders');
    Route::post('/purchase-orders/{purchase_order}/receive', [PurchaseOrderController::class, 'receive'])->name('purchase-orders.receive')->middleware('permission:receive_purchase_orders');
    Route::post('/purchase-orders/{purchase_order}/cancel', [PurchaseOrderController::class, 'cancel'])->name('purchase-orders.cancel')->middleware('permission:cancel_purchase_orders');

    Route::get('/products', [ProductController::class, 'index'])->name('products.index')->middleware('permission:view_products');
    Route::get('/products/create', [ProductController::class, 'create'])->name('products.create')->middleware('permission:create_products');
    Route::post('/products', [ProductController::class, 'store'])->name('products.store')->middleware('permission:create_products');
    Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit')->middleware('permission:edit_products');
    Route::match(['put', 'patch'], '/products/{product}', [ProductController::class, 'update'])->name('products.update')->middleware('permission:edit_products');
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy')->middleware('permission:delete_products');
    Route::post('/products/{product}/stock', [ProductController::class, 'updateStock'])->name('products.stock')->middleware('permission:adjust_stock');

    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index')->middleware('permission:manage_categories');
    Route::get('/categories/create', [CategoryController::class, 'create'])->name('categories.create')->middleware('permission:manage_categories');
    Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store')->middleware('permission:manage_categories');
    Route::get('/categories/{category}/edit', [CategoryController::class, 'edit'])->name('categories.edit')->middleware('permission:manage_categories');
    Route::match(['put', 'patch'], '/categories/{category}', [CategoryController::class, 'update'])->name('categories.update')->middleware('permission:manage_categories');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy')->middleware('permission:manage_categories');

    Route::get('/units', [UnitController::class, 'index'])->name('units.index')->middleware('permission:manage_units');
    Route::get('/units/create', [UnitController::class, 'create'])->name('units.create')->middleware('permission:manage_units');
    Route::post('/units', [UnitController::class, 'store'])->name('units.store')->middleware('permission:manage_units');
    Route::get('/units/{unit}/edit', [UnitController::class, 'edit'])->name('units.edit')->middleware('permission:manage_units');
    Route::match(['put', 'patch'], '/units/{unit}', [UnitController::class, 'update'])->name('units.update')->middleware('permission:manage_units');
    Route::delete('/units/{unit}', [UnitController::class, 'destroy'])->name('units.destroy')->middleware('permission:manage_units');

    Route::get('/warehouses', [WarehouseController::class, 'index'])->name('warehouses.index')->middleware('permission:view_warehouses');
    Route::get('/warehouses/create', [WarehouseController::class, 'create'])->name('warehouses.create')->middleware('permission:manage_warehouses');
    Route::post('/warehouses', [WarehouseController::class, 'store'])->name('warehouses.store')->middleware('permission:manage_warehouses');
    Route::get('/warehouses/{warehouse}/edit', [WarehouseController::class, 'edit'])->name('warehouses.edit')->middleware('permission:manage_warehouses');
    Route::match(['put', 'patch'], '/warehouses/{warehouse}', [WarehouseController::class, 'update'])->name('warehouses.update')->middleware('permission:manage_warehouses');
    Route::delete('/warehouses/{warehouse}', [WarehouseController::class, 'destroy'])->name('warehouses.destroy')->middleware('permission:manage_warehouses');

    Route::get('/stock', [StockController::class, 'index'])->name('stock.index')->middleware('permission:view_stock');
    Route::get('/stock/movements', [StockController::class, 'movements'])->name('stock.movements')->middleware('permission:view_stock');
    Route::post('/stock/adjust', [StockController::class, 'adjust'])->name('stock.adjust')->middleware('permission:adjust_stock');

    Route::get('/tickets', [TicketController::class, 'index'])->name('tickets.index')->middleware('permission:view_tickets');
    Route::get('/tickets/create', [TicketController::class, 'create'])->name('tickets.create')->middleware('permission:create_tickets');
    Route::post('/tickets', [TicketController::class, 'store'])->name('tickets.store')->middleware('permission:create_tickets');
    Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show')->middleware('permission:view_tickets');
    Route::post('/tickets/{ticket}/reply', [TicketController::class, 'reply'])->name('tickets.reply')->middleware('permission:reply_tickets');
    Route::post('/tickets/{ticket}/assign', [TicketController::class, 'assign'])->name('tickets.assign')->middleware('permission:assign_tickets');
    Route::post('/tickets/{ticket}/status', [TicketController::class, 'status'])->name('tickets.status')->middleware('permission:reply_tickets');
    Route::delete('/tickets/{ticket}', [TicketController::class, 'destroy'])->name('tickets.destroy')->middleware('permission:delete_tickets');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

    Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index')->middleware('permission:view_employees');
    Route::get('/employees/create', [EmployeeController::class, 'create'])->name('employees.create')->middleware('permission:create_employees');
    Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store')->middleware('permission:create_employees');
    Route::get('/employees/{employee}/edit', [EmployeeController::class, 'edit'])->name('employees.edit')->middleware('permission:edit_employees');
    Route::match(['put', 'patch'], '/employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update')->middleware('permission:edit_employees');
    Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy'])->name('employees.destroy')->middleware('permission:delete_employees');

    Route::get('/departments', [DepartmentController::class, 'index'])->name('departments.index')->middleware('permission:manage_departments');
    Route::get('/departments/create', [DepartmentController::class, 'create'])->name('departments.create')->middleware('permission:manage_departments');
    Route::post('/departments', [DepartmentController::class, 'store'])->name('departments.store')->middleware('permission:manage_departments');
    Route::get('/departments/{department}/edit', [DepartmentController::class, 'edit'])->name('departments.edit')->middleware('permission:manage_departments');
    Route::match(['put', 'patch'], '/departments/{department}', [DepartmentController::class, 'update'])->name('departments.update')->middleware('permission:manage_departments');
    Route::delete('/departments/{department}', [DepartmentController::class, 'destroy'])->name('departments.destroy')->middleware('permission:manage_departments');

    Route::get('/payroll', [PayrollController::class, 'index'])->name('payroll.index')->middleware('permission:view_payroll');
    Route::get('/payroll/create', [PayrollController::class, 'create'])->name('payroll.create')->middleware('permission:run_payroll');
    Route::post('/payroll/run', [PayrollController::class, 'run'])->name('payroll.run')->middleware('permission:run_payroll');
    Route::get('/payroll/{payroll}', [PayrollController::class, 'show'])->name('payroll.show')->middleware('permission:view_payroll');
    Route::match(['put', 'patch'], '/payroll/{payroll}', [PayrollController::class, 'update'])->name('payroll.update')->middleware('permission:run_payroll');
    Route::post('/payroll/{payroll}/approve', [PayrollController::class, 'approve'])->name('payroll.approve')->middleware('permission:approve_payroll');
    Route::post('/payroll/{payroll}/pay', [PayrollController::class, 'pay'])->name('payroll.pay')->middleware('permission:pay_payroll');
    Route::delete('/payroll/{payroll}', [PayrollController::class, 'destroy'])->name('payroll.destroy')->middleware('permission:run_payroll');

    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index')->middleware('permission:view_attendance');
    Route::get('/attendance/create', [AttendanceController::class, 'create'])->name('attendance.create')->middleware('permission:manage_attendance');
    Route::post('/attendance', [AttendanceController::class, 'store'])->name('attendance.store')->middleware('permission:manage_attendance');
    Route::get('/attendance/{attendance}/edit', [AttendanceController::class, 'edit'])->name('attendance.edit')->middleware('permission:manage_attendance');
    Route::match(['put', 'patch'], '/attendance/{attendance}', [AttendanceController::class, 'update'])->name('attendance.update')->middleware('permission:manage_attendance');
    Route::delete('/attendance/{attendance}', [AttendanceController::class, 'destroy'])->name('attendance.destroy')->middleware('permission:manage_attendance');

    Route::prefix('reports')->middleware('permission:view_reports')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/sales', [ReportController::class, 'sales'])->name('reports.sales');
        Route::get('/inventory', [ReportController::class, 'inventory'])->name('reports.inventory');
        Route::get('/profit', [ReportController::class, 'profit'])->name('reports.profit');
        Route::get('/expenses', [ReportController::class, 'expenses'])->name('reports.expenses');
        Route::get('/overdue', [ReportController::class, 'overdue'])->name('reports.overdue');
    });

    Route::get('/users', [UserController::class, 'index'])->name('users.index')->middleware('permission:view_users');
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create')->middleware('permission:create_users');
    Route::post('/users', [UserController::class, 'store'])->name('users.store')->middleware('permission:create_users');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit')->middleware('permission:edit_users');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update')->middleware('permission:edit_users');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy')->middleware('permission:delete_users');

    Route::get('/roles', [RoleController::class, 'index'])->name('roles.index')->middleware('permission:manage_roles');
    Route::get('/roles/create', [RoleController::class, 'create'])->name('roles.create')->middleware('permission:manage_roles');
    Route::post('/roles', [RoleController::class, 'store'])->name('roles.store')->middleware('permission:manage_roles');
    Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit')->middleware('permission:manage_roles');
    Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update')->middleware('permission:manage_roles');
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy')->middleware('permission:manage_roles');

    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index')->middleware('permission:view_audit_logs');
    Route::get('/audit-logs/{auditLog}', [AuditLogController::class, 'show'])->name('audit-logs.show')->middleware('permission:view_audit_logs');
});

require __DIR__.'/auth.php';
