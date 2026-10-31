<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Configuration initiale de PRODUCTION : rôles, permissions, devises, numérotation, catégories de frais
 * (sans aucun montant), compte d'administration technique. Les structures académiques, banques, comptes
 * et barèmes sont saisis par l'institution. Données fictives : DemoSeeder (base séparée).
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([RolePermissionSeeder::class, ReferenceSeeder::class]);
    }
}
