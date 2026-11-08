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
        foreach (PermissionRegistry::PERMISSIONS as $group => $perms) {
            foreach ($perms as $slug => $name) {
                Permission::query()->updateOrCreate(['slug' => $slug], ['name' => $name, 'group' => $group]);
            }
        }

        $roles = [
            Role::ADMIN => ['Administrateur', 'Accès complet, utilisateurs, configuration et journal d\'audit'],
            Role::MANAGER => ['Gérant', 'Supervision des ventes, stocks, dépenses, inventaires et rapports'],
            Role::CASHIER => ['Caissier', 'Ventes, encaissements, reçus et consultation de sa caisse'],
            Role::STOREKEEPER => ['Magasinier', 'Produits, approvisionnements, mouvements de stock et inventaires'],
        ];
        foreach ($roles as $slug => [$name, $description]) {
            $role = Role::query()->firstOrCreate(['slug' => $slug], ['name' => $name, 'description' => $description, 'is_system' => true]);
            // On n'écrase pas une configuration modifiée par l'administrateur : seulement à la création.
            if ($role->wasRecentlyCreated && isset(PermissionRegistry::ROLE_DEFAULTS[$slug])) {
                $role->permissions()->sync(Permission::query()->whereIn('slug', PermissionRegistry::ROLE_DEFAULTS[$slug])->pluck('id'));
            }
        }
    }
}
