<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreUserRequest;
use App\Http\Requests\Settings\UpdateUserRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::query()
            ->with('role:id,name,slug')
            ->search($request->get('search'))
            ->when($request->filled('role_id'), fn ($q) => $q->where('role_id', $request->get('role_id')))
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('settings.users.index', [
            'users' => $users,
            'roles' => Role::orderBy('name')->get(['id', 'name']),
            'activeRole' => $request->get('role_id'),
            'activeStatus' => $request->filled('is_active') ? ($request->boolean('is_active') ? '1' : '0') : '',
        ]);
    }

    public function create(): View
    {
        return view('settings.users.create', [
            'roles' => Role::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = User::create($data + [
            'password' => $data['password'],
            'is_active' => $request->boolean('is_active', true),
            'email_verified_at' => now(),
        ]);

        AuditLogger::log('created', $user, null, ['name' => $user->name, 'email' => $user->email, 'role_id' => $user->role_id]);

        return redirect()->route('users.index')->with('success', 'تم إضافة المستخدم بنجاح');
    }

    public function edit(User $user): View
    {
        return view('settings.users.edit', [
            'user' => $user,
            'roles' => Role::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->preventSelfDeactivation($request, $user);

        $data = $request->validated();
        unset($data['password_confirmation']);

        $old = $user->only(['name', 'email', 'role_id', 'is_active']);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data + ['is_active' => $request->boolean('is_active', true)]);

        AuditLogger::log('updated', $user, $old, $user->fresh()->only(['name', 'email', 'role_id', 'is_active']));

        return redirect()->route('users.index')->with('success', 'تم تحديث المستخدم بنجاح');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'لا يمكنك حذف حسابك الحالي');
        }

        AuditLogger::log('deleted', $user, $user->toArray(), null);
        $user->delete();

        return redirect()->route('users.index')->with('success', 'تم حذف المستخدم');
    }

    private function preventSelfDeactivation(UpdateUserRequest $request, User $user): void
    {
        if ($user->id === auth()->id() && ! $request->boolean('is_active', true)) {
            abort(422, 'لا يمكنك تعطيل حسابك الحالي');
        }
    }
}