<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    private function admin(): User
    {
        return User::where('email', 'admin@crm.test')->firstOrFail();
    }

    private function rep(): User
    {
        return User::where('email', 'rep@crm.test')->firstOrFail();
    }

    public function test_admin_can_view_users(): void
    {
        $this->actingAs($this->admin())
            ->get('/users')
            ->assertOk()
            ->assertSee('المستخدمون');
    }

    public function test_rep_cannot_view_users(): void
    {
        $this->actingAs($this->rep())
            ->get('/users')
            ->assertForbidden();
    }

    public function test_admin_can_create_user(): void
    {
        $role = Role::where('slug', 'sales-rep')->firstOrFail();

        $this->actingAs($this->admin())
            ->post('/users', [
                'name' => 'عمر جديد',
                'email' => 'omar@crm.test',
                'password' => 'password',
                'password_confirmation' => 'password',
                'role_id' => $role->id,
                'is_active' => '1',
            ])
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', ['email' => 'omar@crm.test', 'role_id' => $role->id]);
    }

    public function test_create_user_requires_unique_email(): void
    {
        $this->actingAs($this->admin())
            ->post('/users', [
                'name' => 'مكرر',
                'email' => 'admin@crm.test',
                'password' => 'password',
                'password_confirmation' => 'password',
                'role_id' => Role::where('slug', 'sales-rep')->value('id'),
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_admin_cannot_delete_own_account(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->delete('/users/'.$admin->id)
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_admin_can_disable_user(): void
    {
        $target = User::where('email', 'rep@crm.test')->firstOrFail();
        $role = Role::where('slug', 'sales-rep')->firstOrFail();

        $this->actingAs($this->admin())
            ->put('/users/'.$target->id, [
                'name' => $target->name,
                'email' => $target->email,
                'role_id' => $role->id,
                'is_active' => '0',
            ])
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', ['id' => $target->id, 'is_active' => false]);
    }

    public function test_admin_can_view_roles(): void
    {
        $this->actingAs($this->admin())
            ->get('/roles')
            ->assertOk()
            ->assertSee('الأدوار والصلاحيات');
    }

    public function test_system_roles_marked_is_system(): void
    {
        $this->assertTrue(Role::where('slug', 'admin')->value('is_system'));
    }

    public function test_system_role_cannot_be_deleted(): void
    {
        $adminRole = Role::where('slug', 'admin')->firstOrFail();

        $this->actingAs($this->admin())
            ->delete('/roles/'.$adminRole->id)
            ->assertSessionHas('error');

        $this->assertDatabaseHas('roles', ['id' => $adminRole->id]);
    }

    public function test_admin_can_create_role_with_permissions(): void
    {
        $perm = Permission::where('slug', 'view_reports')->firstOrFail();

        $this->actingAs($this->admin())
            ->post('/roles', [
                'name' => 'مشرف تقارير',
                'slug' => 'report-viewer',
                'description' => 'يطلع على التقارير فقط',
                'permissions' => [$perm->id],
            ])
            ->assertRedirect(route('roles.index'));

        $role = Role::where('slug', 'report-viewer')->firstOrFail();
        $this->assertTrue($role->hasPermission('view_reports'));
    }

    public function test_admin_can_update_custom_role_permissions(): void
    {
        $role = Role::create(['name' => 'دور مؤقت', 'slug' => 'temp-role']);
        $view = Permission::where('slug', 'view_dashboard')->firstOrFail();
        $edit = Permission::where('slug', 'view_leads')->firstOrFail();

        $this->actingAs($this->admin())
            ->put('/roles/'.$role->id, [
                'name' => 'دور مؤقت محرر',
                'slug' => 'temp-role',
                'description' => 'معدل',
                'permissions' => [$view->id, $edit->id],
            ])
            ->assertRedirect(route('roles.index'));

        $role->refresh();

        $this->assertSame('دور مؤقت محرر', $role->name);
        $this->assertTrue($role->hasPermission('view_dashboard'));
        $this->assertTrue($role->hasPermission('view_leads'));
    }

    public function test_delete_role_attached_to_users_is_blocked(): void
    {
        $role = Role::create(['name' => 'موظف', 'slug' => 'staff']);
        User::create([
            'name' => 'موظف واحد',
            'email' => 'staff@crm.test',
            'password' => 'password',
            'role_id' => $role->id,
        ]);

        $this->actingAs($this->admin())
            ->delete('/roles/'.$role->id)
            ->assertSessionHas('error');

        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    public function test_admin_can_view_audit_logs_and_creates_entries(): void
    {
        $role = Role::where('slug', 'sales-rep')->firstOrFail();

        $this->actingAs($this->admin())
            ->post('/users', [
                'name' => 'سجل جديد',
                'email' => 'loguser@crm.test',
                'password' => 'password',
                'password_confirmation' => 'password',
                'role_id' => $role->id,
                'is_active' => '1',
            ]);

        $this->actingAs($this->admin())
            ->get('/audit-logs')
            ->assertOk()
            ->assertSee('سجل التدقيق');

        $this->assertTrue(AuditLog::where('action', 'created')->exists());
    }

    public function test_rep_cannot_view_audit_logs(): void
    {
        $this->actingAs($this->rep())
            ->get('/audit-logs')
            ->assertForbidden();
    }

    public function test_audit_log_show_renders(): void
    {
        $log = AuditLog::create([
            'user_id' => $this->admin()->id,
            'action' => 'created',
            'model_type' => User::class,
            'model_id' => 1,
            'old_values' => null,
            'new_values' => ['name' => 'اختبار'],
        ]);

        $this->actingAs($this->admin())
            ->get('/audit-logs/'.$log->id)
            ->assertOk()
            ->assertSee('تفاصيل السجل');
    }
}