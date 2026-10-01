<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoleRequest;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * The Super Admin role never appears here - it is not delegable and its
 * holders are managed from each admin's own Settings page (see
 * StaffController for the matching rule on Staff).
 */
class RoleController extends Controller
{
    public function index(): View
    {
        return view('admin.roles', [
            'roles' => Role::where('is_system', false)->withCount('admins')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.create-role', ['role' => null]);
    }

    public function store(RoleRequest $request): JsonResponse
    {
        $role = Role::create([
            'name' => $request->name(),
            'slug' => $this->uniqueSlug($request->name()),
            'tag' => 'Custom Role',
            'description' => $request->description(),
            'is_active' => true,
        ]);

        $role->syncPermissions($request->permissionKeys());

        return response()->json(['message' => 'Role created.', 'data' => ['id' => $role->id]], 201);
    }

    public function show(Role $role): View
    {
        abort_if($role->isSuperAdmin(), 404);

        $role->loadCount('admins');

        return view('admin.role-details', [
            'role' => $role,
            'admins' => $role->admins()->orderBy('name')->get(),
        ]);
    }

    public function edit(Role $role): View
    {
        abort_if($role->isSuperAdmin(), 404);

        return view('admin.create-role', ['role' => $role]);
    }

    public function update(RoleRequest $request, Role $role): JsonResponse
    {
        abort_if($role->isSuperAdmin(), 404);

        $role->update([
            'name' => $request->name(),
            'slug' => $this->uniqueSlug($request->name(), $role->id),
            'description' => $request->description(),
        ]);

        $role->syncPermissions($request->permissionKeys());

        return response()->json(['message' => 'Role updated.', 'data' => ['id' => $role->id]]);
    }

    public function toggle(Role $role): JsonResponse
    {
        abort_if($role->isSuperAdmin(), 404);

        $role->update(['is_active' => ! $role->is_active]);

        return response()->json([
            'message' => $role->is_active ? 'Role enabled.' : 'Role disabled.',
            'data' => ['is_active' => $role->is_active],
        ]);
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;

        while (
            Role::where('slug', $slug)
                ->when($ignoreId, fn ($query, $id) => $query->where('id', '!=', $id))
                ->exists()
        ) {
            $slug = $base . '-' . $suffix++;
        }

        return $slug;
    }
}
