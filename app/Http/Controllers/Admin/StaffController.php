<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StaffListRequest;
use App\Http\Requests\Admin\StaffRequest;
use App\Models\Admin;
use App\Models\Role;
use App\Services\Auth\PasswordResetService;
use App\Support\Identifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Super Admin accounts never appear here - they are managed from each admin's
 * own Settings page, not through Staff (see RoleController for the matching
 * rule on the Super Admin role's "Assigned Users").
 */
class StaffController extends Controller
{
    private const PER_PAGE = 15;

    public function index(StaffListRequest $request): View
    {
        $staff = Admin::with('role')
            ->whereHas('role', fn ($query) => $query->where('is_system', false))
            ->when($request->search(), fn ($query, $term) => $this->search($query, $term))
            ->when($request->status(), fn ($query, $status) => $query->where('status', $status->value))
            ->when($request->roleId(), fn ($query, $roleId) => $query->where('role_id', $roleId))
            ->latest('created_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin.staff', [
            'staff' => $staff,
            'total' => Admin::whereHas('role', fn ($query) => $query->where('is_system', false))->count(),
            'roles' => Role::where('is_system', false)->orderBy('name')->get(),
            'filters' => $request->only(['q', 'status', 'role']),
        ]);
    }

    public function create(): View
    {
        return view('admin.create-staff', [
            'admin' => null,
            'roles' => Role::where('is_active', true)->where('is_system', false)->orderBy('name')->get(),
        ]);
    }

    public function store(StaffRequest $request): JsonResponse
    {
        $admin = Admin::create([
            'role_id' => $request->roleId(),
            'name' => $request->name(),
            'email' => $request->email(),
            'mobile' => $request->mobile(),
            'password' => $request->string('password')->toString(),
            'status' => $request->status(),
        ]);

        return response()->json(['message' => 'Staff member created.', 'data' => ['id' => $admin->id]], 201);
    }

    public function show(Admin $admin): View
    {
        $this->guardNotSuperAdmin($admin);

        return view('admin.staff-details', ['member' => $admin->load('role')]);
    }

    public function edit(Admin $admin): View
    {
        $this->guardNotSuperAdmin($admin);

        return view('admin.create-staff', [
            'admin' => $admin,
            'roles' => Role::where('is_system', false)
                ->where(fn ($query) => $query->where('is_active', true)->orWhere('id', $admin->role_id))
                ->orderBy('name')->get(),
        ]);
    }

    public function update(StaffRequest $request, Admin $admin): JsonResponse
    {
        $this->guardNotSuperAdmin($admin);

        $admin->fill([
            'role_id' => $request->roleId(),
            'name' => $request->name(),
            'email' => $request->email(),
            'mobile' => $request->mobile(),
            'status' => $request->status(),
        ]);

        if ($request->filled('password')) {
            $admin->password = $request->string('password')->toString();
        }

        $admin->save();

        return response()->json(['message' => 'Staff member updated.', 'data' => ['id' => $admin->id]]);
    }

    public function toggle(Admin $admin): JsonResponse
    {
        $this->guardNotSuperAdmin($admin);

        $admin->update(['status' => $admin->isActive() ? AdminStatus::Inactive : AdminStatus::Active]);

        return response()->json([
            'message' => $admin->isActive() ? 'Staff member activated.' : 'Staff member deactivated.',
            'data' => ['status' => $admin->status->value],
        ]);
    }

    public function updateRole(Request $request, Admin $admin): JsonResponse
    {
        $this->guardNotSuperAdmin($admin);

        $data = $request->validate([
            'role_id' => ['required', 'integer', 'exists:roles,id'],
        ]);

        $role = Role::findOrFail($data['role_id']);

        if ($role->isSuperAdmin()) {
            return response()->json(['message' => 'The Super Admin role cannot be assigned here.'], 422);
        }

        if (! $role->is_active) {
            return response()->json(['message' => 'This role is disabled and cannot be assigned.'], 422);
        }

        $admin->update(['role_id' => $role->id]);

        return response()->json(['message' => "Role changed to {$role->name}.", 'data' => ['role_id' => $role->id]]);
    }

    public function resetPassword(Admin $admin, PasswordResetService $passwordReset): JsonResponse
    {
        $this->guardNotSuperAdmin($admin);

        $passwordReset->sendOtp(Identifier::parse($admin->email));

        return response()->json(['message' => "A password reset code has been sent to {$admin->email}."]);
    }

    private function guardNotSuperAdmin(Admin $admin): void
    {
        abort_if($admin->isSuperAdmin(), 404);
    }

    private function search($query, string $term)
    {
        // Escape LIKE wildcards so "50%" or "_" search literally.
        $like = '%' . addcslashes($term, '%_\\') . '%';

        return $query->where(fn ($q) => $q
            ->where('name', 'like', $like)
            ->orWhere('email', 'like', $like));
    }
}
