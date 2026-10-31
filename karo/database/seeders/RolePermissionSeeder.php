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
        foreach (PermissionRegistry::PERMISSIONS as $module => $perms) {
            foreach ($perms as $slug => [$name, $sensitive]) {
                Permission::query()->updateOrCreate(['slug' => $slug], ['name' => $name, 'module' => $module, 'is_sensitive' => $sensitive]);
            }
        }
        foreach (PermissionRegistry::ROLES as $slug => [$name, $description, $requires2fa, $perms]) {
            $role = Role::query()->firstOrCreate(['slug' => $slug], ['name' => $name, 'description' => $description, 'requires_2fa' => $requires2fa, 'is_system' => true]);
            if ($role->wasRecentlyCreated) {
                $role->permissions()->sync(Permission::query()->whereIn('slug', $perms)->pluck('id'));
            }
        }
    }
}
