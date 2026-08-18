<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreRoleRequest;
use App\Http\Requests\Settings\UpdateRoleRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        $roles = Role::withCount('users')
            ->withCount('permissions')
            ->orderBy('name')
            ->paginate(15);

        return view('settings.roles.index', ['roles' => $roles]);
    }

    public function create(): View
    {
        return view('settings.roles.create', [
            'groups' => Permission::orderBy('group')->orderBy('name')->get()->groupBy('group'),
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $role = Role::create([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
        ]);

        $role->permissions()->sync($data['permissions'] ?? []);

        AuditLogger::log('created', $role, null, $role->toArray());

        return redirect()->route('roles.index')->with('success', 'تم إضافة الدور بنجاح');
    }

    public function edit(Role $role): View
    {
        return view('settings.roles.edit', [
            'role' => $role,
            'groups' => Permission::orderBy('group')->orderBy('name')->get()->groupBy('group'),
            'selected' => $role->permissions->pluck('id')->all(),
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $data = $request->validated();

        $old = $role->only(['name', 'description']);

        $role->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        if (! $role->is_system) {
            $role->update(['slug' => $data['slug']]);
        }

        $role->permissions()->sync($data['permissions'] ?? []);

        AuditLogger::log('updated', $role, $old, $role->fresh()->only(['name', 'description']));

        return redirect()->route('roles.index')->with('success', 'تم تحديث الدور بنجاح');
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->is_system) {
            return back()->with('error', 'لا يمكن حذف دور من أدوار النظام');
        }

        if ($role->users()->exists()) {
            return back()->with('error', 'لا يمكن حذف دور مرتبط بمستخدمين');
        }

        AuditLogger::log('deleted', $role, $role->toArray(), null);
        $role->delete();

        return redirect()->route('roles.index')->with('success', 'تم حذف الدور');
    }
}