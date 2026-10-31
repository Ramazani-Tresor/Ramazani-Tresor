<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\DocumentSequence;
use App\Models\FeeCategory;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRoleAssignment;
use App\Services\NumberingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ReferenceSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([['USD', 'Dollar américain', '$'], ['CDF', 'Franc congolais', 'FC']] as [$code, $name, $symbol]) {
            Currency::query()->firstOrCreate(['code' => $code], ['name' => $name, 'symbol' => $symbol, 'is_active' => true]);
        }
        foreach (NumberingService::DEFAULTS as $type => [$label, $prefix]) {
            DocumentSequence::query()->firstOrCreate(['type' => $type], ['label' => $label, 'prefix' => $prefix, 'next_number' => 1, 'padding' => 6, 'yearly_reset' => true, 'current_year' => (int) now()->year]);
        }
        // Catégories de frais de référence : libellés et règles d'application, SANS montant (les barèmes sont saisis et approuvés).
        $categories = [
            ['MINERVAL', 'Minerval', 'mandatory', 'enrollment', null, false, null, null, 10],
            ['CONNEXES', 'Frais connexes', 'mandatory', 'enrollment', null, false, null, null, 20],
            ['INSCR-NOUV', 'Inscription des nouveaux étudiants', 'mandatory', 'enrollment', ['new'], false, null, null, 5],
            ['REINSCR', 'Réinscription des anciens étudiants', 'mandatory', 'enrollment', ['returning', 'finalist'], false, null, null, 6],
            ['ENTERINEMENT', 'Entérinement du diplôme', 'conditional', 'enrollment', ['finalist'], true, null, null, 30],
            ['EXAM-S1', 'Inscription aux examens du premier semestre', 'mandatory', 'exam_session', null, false, 1, 'ordinary', 40],
            ['RATT-S1', 'Inscription au rattrapage du premier semestre', 'conditional', 'exam_session', null, false, 1, 'resit', 41],
            ['EXAM-S2', 'Inscription aux examens du deuxième semestre', 'mandatory', 'exam_session', null, false, 2, 'ordinary', 42],
            ['RATT-S2', 'Inscription au rattrapage du deuxième semestre', 'conditional', 'exam_session', null, false, 2, 'resit', 43],
        ];
        foreach ($categories as [$code, $label, $nature, $trigger, $statuses, $finalOnly, $sem, $type, $prio]) {
            FeeCategory::query()->firstOrCreate(['code' => $code], [
                'label' => $label, 'nature' => $nature, 'trigger' => $trigger, 'applicable_statuses' => $statuses,
                'final_promotions_only' => $finalOnly, 'session_semester' => $sem, 'session_type' => $type, 'priority' => $prio, 'is_active' => true,
            ]);
        }
        if (! User::query()->exists()) {
            $password = env('INITIAL_ADMIN_PASSWORD') ?: Str::password(16);
            $user = User::create(['name' => 'Administrateur technique', 'username' => 'admin', 'password' => $password, 'is_active' => true, 'must_change_password' => true]);
            UserRoleAssignment::create(['user_id' => $user->id, 'role_id' => Role::query()->where('slug', 'tech_admin')->value('id')]);
            if (! env('INITIAL_ADMIN_PASSWORD')) {
                $this->command?->warn("Compte « admin » créé avec le mot de passe temporaire : {$password} (à changer à la première connexion).");
            }
        }
    }
}
