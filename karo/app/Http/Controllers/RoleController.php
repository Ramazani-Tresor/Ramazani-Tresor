<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessException;
use App\Models\Permission;
use App\Models\Role;
use App\Services\AuditService;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index()
    {
        return view('users.roles', ['roles' => Role::query()->with('permissions')->withCount('assignments')->orderBy('id')->get(),
            'permissions' => Permission::query()->orderBy('module')->orderBy('id')->get()->groupBy('module')]);
    }

    public function update(Request $request, Role $role)
    {
        $d = $request->validate(['permissions' => 'nullable|array', 'permissions.*' => 'exists:permissions,id', 'requires_2fa' => 'nullable|boolean']);
        $new = Permission::query()->whereIn('id', $d['permissions'] ?? [])->get();
        // Séparation administration technique / approbations financières au niveau du rôle.
        if ($new->contains('slug', 'users.manage') || $new->contains('slug', 'roles.manage')) {
            if ($new->where('is_sensitive', true)->isNotEmpty()) {
                throw new BusinessException('Un rôle ne peut pas cumuler l’administration technique (utilisateurs, rôles) et des approbations financières.');
            }
        }
        if ($request->user()->assignments()->where('role_id', $role->id)->exists()) {
            throw new BusinessException('Vous ne pouvez pas modifier un rôle qui vous est attribué.');
        }
        $before = $role->permissions()->pluck('slug')->all();
        $role->permissions()->sync($new->pluck('id'));
        $role->update(['requires_2fa' => $request->boolean('requires_2fa')]);
        AuditService::log('role.update', $role, "Permissions du rôle {$role->name} modifiées", ['permissions' => $before], ['permissions' => $new->pluck('slug')->all(), 'requires_2fa' => $role->requires_2fa]);

        return back()->with('success', "Rôle « {$role->name} » mis à jour (effet immédiat).");
    }
}
