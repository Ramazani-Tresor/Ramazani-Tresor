<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/** Configuration initiale (production) : rôles, permissions, référentiels sans montants, compte administrateur. */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([RolePermissionSeeder::class, ReferenceSeeder::class]);
    }
}
