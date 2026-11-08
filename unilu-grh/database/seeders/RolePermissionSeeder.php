<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Support\PermissionRegistry;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PermissionRegistry::PERMISSIONS as $slug => [$name, $module, $sensitive]) {
            Permission::query()->updateOrCreate(['slug' => $slug], ['name' => $name, 'module' => $module, 'is_sensitive' => $sensitive]);
        }
        $ids = Permission::query()->pluck('id', 'slug');
        foreach (PermissionRegistry::ROLES as $slug => [$name, $description]) {
            $role = Role::query()->firstOrCreate(['slug' => $slug], ['name' => $name, 'description' => $description, 'is_system' => true]);
            // Les permissions d'un rôle existant ne sont pas réécrites (elles ont pu être adaptées par l'administration).
            if ($role->wasRecentlyCreated || $slug === 'super_admin') {
                $role->permissions()->sync($ids->only(PermissionRegistry::permissionsFor($slug))->values());
            }
        }
    }
}
