<?php

namespace Database\Seeders;

use App\Models\DocumentSequence;
use App\Models\ExpenseCategory;
use App\Models\PaymentMethod;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Services\NumberingService;
use Illuminate\Database\Seeder;

class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['cash', 'Espèces', 'cash', 1],
            ['mobile_money', 'Mobile Money', 'electronic', 2],
            ['bank_transfer', 'Virement bancaire', 'electronic', 3],
            ['card', 'Carte bancaire', 'electronic', 4],
        ] as [$code, $name, $kind, $order]) {
            PaymentMethod::query()->firstOrCreate(['code' => $code], ['name' => $name, 'kind' => $kind, 'sort_order' => $order, 'is_active' => true]);
        }

        foreach ([
            ['Pièce', 'pce', false], ['Kilogramme', 'kg', true], ['Litre', 'L', true], ['Mètre', 'm', true],
            ['Carton', 'ctn', false], ['Sac', 'sac', false], ['Heure', 'h', true], ['Forfait', 'forf.', false],
        ] as [$name, $symbol, $decimal]) {
            Unit::query()->firstOrCreate(['name' => $name], ['symbol' => $symbol, 'allows_decimal' => $decimal]);
        }

        foreach (NumberingService::DEFAULTS as $type => [$label, $prefix]) {
            DocumentSequence::query()->firstOrCreate(['type' => $type], [
                'label' => $label, 'prefix' => $prefix, 'next_number' => 1, 'padding' => 6,
                'yearly_reset' => $type !== 'customer', 'current_year' => (int) now()->year,
            ]);
        }

        foreach (['Loyer', 'Électricité et eau', 'Salaires', 'Transport', 'Communication', 'Fournitures de bureau', 'Entretien et réparations', 'Taxes et redevances', 'Divers'] as $name) {
            ExpenseCategory::query()->firstOrCreate(['name' => $name]);
        }

        if (! User::query()->exists()) {
            User::create([
                'name' => 'Administrateur',
                'username' => 'admin',
                'email' => null,
                'password' => env('INITIAL_ADMIN_PASSWORD') ?: 'admin12345',
                'role_id' => Role::query()->where('slug', Role::ADMIN)->value('id'),
                'is_active' => true,
            ]);
        }
    }
}
