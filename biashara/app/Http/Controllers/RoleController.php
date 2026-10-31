<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use App\Services\AuditService;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index()
    {
        return view('roles.index', [
            'roles' => Role::query()->with('permissions')->withCount('users')->orderBy('id')->get(),
            'permissions' => Permission::query()->orderBy('group')->orderBy('id')->get()->groupBy('group'),
        ]);
    }

    public function update(Request $request, Role $role)
    {
        abort_if($role->slug === Role::ADMIN, 403, 'Le rôle Administrateur dispose toujours de toutes les permissions.');
        $data = $request->validate(['permissions' => 'array', 'permissions.*' => 'exists:permissions,id']);
        $before = $role->permissions()->pluck('slug')->all();
        $role->permissions()->sync($data['permissions'] ?? []);
        $after = $role->permissions()->pluck('slug')->all();
        AuditService::log('role.permissions', $role, "Permissions du rôle {$role->name} modifiées", ['permissions' => $before], ['permissions' => $after]);

        return back()->with('success', "Permissions du rôle « {$role->name} » enregistrées.");
    }
}
