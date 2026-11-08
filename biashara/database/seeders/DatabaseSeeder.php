<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Données indispensables au fonctionnement (rôles, permissions, modes de paiement, unités,
     * numérotation, administrateur initial). Les données de démonstration sont séparées :
     *   php artisan db:seed --class=DemoSeeder
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            ReferenceDataSeeder::class,
        ]);
    }
}
