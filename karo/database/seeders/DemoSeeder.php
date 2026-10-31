<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Cycle;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\ExamSession;
use App\Models\Faculty;
use App\Models\FeeCategory;
use App\Models\Program;
use App\Models\Promotion;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use App\Models\UserRoleAssignment;
use App\Services\AdjustmentService;
use App\Services\ChargeService;
use App\Services\FeeScheduleService;
use App\Services\PaymentService;
use App\Services\TotpService;
use App\Support\Money;
use App\Support\Settings;
use Brick\Math\RoundingMode;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * DONNÉES DE DÉMONSTRATION ENTIÈREMENT FICTIVES — à charger UNIQUEMENT dans une base dédiée (ex. karo_demo).
 * Refuse de s'exécuter si KARO_DEMO n'est pas à true ou en environnement de production.
 * Tous les noms comportent la mention « (fictif) » ou un matricule préfixé DEMO.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! config('karo.demo_mode') || app()->environment('production')) {
            $this->command?->error('Refusé : définissez KARO_DEMO=true sur une base de démonstration dédiée (jamais en production).');

            return;
        }
        $this->call(DatabaseSeeder::class);
        Settings::set(['university_name' => 'Université Démo de Lubumbashi (fictive)', 'university_acronym' => 'UDL-DÉMO', 'university_address' => 'Campus fictif, Lubumbashi',
            'signatory_1_name' => 'Pr. A. Budget (fictif)', 'signatory_2_name' => 'M. B. Comptable (fictif)', 'demo_mode' => '1', 'student_portal_enabled' => '1']);

        $totp = app(TotpService::class);
        $mk = function (string $username, string $name, string $role, ?int $facultyId = null) {
            $u = User::query()->firstOrCreate(['username' => $username], ['name' => $name.' (fictif)', 'password' => 'Demo!2026karo', 'is_active' => true]);
            UserRoleAssignment::query()->firstOrCreate(['user_id' => $u->id, 'role_id' => Role::query()->where('slug', $role)->value('id'), 'faculty_id' => $facultyId]);
            // En démonstration, le second facteur des rôles sensibles est préconfiguré avec une clé connue (DOCUMENTÉE) : à ne jamais faire en production.
            if (Role::query()->where('slug', $role)->value('requires_2fa') && ! $u->two_factor_secret) {
                $u->forceFill(['two_factor_secret' => 'KARODEMOKAROD2FA', 'two_factor_confirmed_at' => now()])->save();
            }

            return $u->fresh();
        };
        $admin = User::query()->where('username', 'admin')->first();
        $admin?->forceFill(['password' => 'Demo!2026karo', 'must_change_password' => false, 'two_factor_secret' => 'KARODEMOKAROD2FA', 'two_factor_confirmed_at' => now()])->save();

        $cycleL = Cycle::query()->firstOrCreate(['code' => 'LMD-L'], ['name' => 'Licence', 'duration_years' => 3]);
        $cycleM = Cycle::query()->firstOrCreate(['code' => 'LMD-M'], ['name' => 'Master', 'duration_years' => 2]);
        $structure = [
            ['DRT', 'Faculté de Droit (fictive)', 'DPR', 'Droit privé', [['DRT-PRIV', 'Droit privé', $cycleL, [['DRT-B1', 'Bac1', false], ['DRT-B2', 'Bac2', false], ['DRT-B3', 'Bac3', true]]]]],
            ['SCI', 'Faculté des Sciences (fictive)', 'INF', 'Informatique', [['SCI-INF', 'Informatique', $cycleL, [['INF-B1', 'Bac1', false], ['INF-B3', 'Bac3', true]]], ['SCI-INFM', 'Informatique (Master)', $cycleM, [['INF-M2', 'Master2', true]]]]],
            ['MED', 'Faculté de Médecine (fictive)', 'MGN', 'Médecine générale', [['MED-GEN', 'Médecine', $cycleL, [['MED-B1', 'Bac1', false]]]]],
        ];
        $promos = [];
        foreach ($structure as [$fc, $fn, $dc, $dn, $programs]) {
            $f = Faculty::query()->firstOrCreate(['code' => $fc], ['name' => $fn]);
            $d = Department::query()->firstOrCreate(['faculty_id' => $f->id, 'code' => $dc], ['name' => $dn]);
            foreach ($programs as [$pc, $pn, $cy, $levels]) {
                $p = Program::query()->firstOrCreate(['code' => $pc], ['department_id' => $d->id, 'cycle_id' => $cy->id, 'name' => $pn]);
                foreach ($levels as $i => [$code, $level, $final]) {
                    $promos[$code] = Promotion::query()->firstOrCreate(['code' => $code], ['program_id' => $p->id, 'level' => $level, 'name' => $pn.' '.$level, 'is_final' => $final, 'sort_order' => $i + 1]);
                }
            }
        }
        $faculties = Faculty::query()->get()->keyBy('code');
        $budget = $mk('budget', 'Administratrice du budget', 'budget_admin');
        $budget2 = $mk('budget2', 'Adjoint au budget', 'budget_admin');
        $clerk = $mk('comptable', 'Comptable de saisie', 'entry_clerk');
        $clerkDrt = $mk('comptable.droit', 'Comptable de la Faculté de Droit', 'entry_clerk', $faculties['DRT']->id);
        $validator = $mk('controleur', 'Contrôleur', 'validator');
        $mk('recteur', 'Recteur', 'rector');
        $mk('sga', 'Secrétaire général académique', 'academic_secretary');
        $mk('auditeur', 'Auditeur', 'auditor');

        $prev = AcademicYear::query()->firstOrCreate(['code' => '2025-2026'], ['starts_on' => '2025-10-01', 'ends_on' => '2026-09-30', 'status' => 'open']);
        $year = AcademicYear::query()->firstOrCreate(['code' => '2026-2027'], ['starts_on' => '2026-10-01', 'ends_on' => '2027-09-30', 'status' => 'open', 'is_current' => true, 'previous_year_id' => $prev->id]);
        AcademicYear::query()->firstOrCreate(['code' => '2027-2028'], ['starts_on' => '2027-10-01', 'ends_on' => '2028-09-30', 'status' => 'planned', 'previous_year_id' => $year->id]);
        $year->update(['is_current' => true]);

        $bank = Bank::query()->firstOrCreate(['code' => 'BQD'], ['name' => 'Banque Démo du Katanga (fictive)']);
        $usd = BankAccount::query()->firstOrCreate(['account_number' => 'DEMO-0001-USD'], ['bank_id' => $bank->id, 'label' => 'Frais académiques USD', 'currency' => 'USD']);
        $cdf = BankAccount::query()->firstOrCreate(['account_number' => 'DEMO-0002-CDF'], ['bank_id' => $bank->id, 'label' => 'Frais connexes CDF', 'currency' => 'CDF']);
        $cats = FeeCategory::query()->get()->keyBy('code');
        foreach ($cats as $c) {
            $c->bankAccounts()->sync($c->code === 'CONNEXES' ? [$cdf->id] : [$usd->id]);
        }
        $s1 = ExamSession::query()->firstOrCreate(['academic_year_id' => $year->id, 'semester' => 1, 'type' => 'ordinary'], ['name' => 'Examens du premier semestre', 'threshold_percent' => 50, 'payment_deadline' => '2027-01-15', 'fee_category_ids' => [$cats['MINERVAL']->id, $cats['EXAM-S1']->id]]);
        ExamSession::query()->firstOrCreate(['academic_year_id' => $year->id, 'semester' => 1, 'type' => 'resit'], ['name' => 'Rattrapage du premier semestre', 'threshold_percent' => 100, 'fee_category_ids' => [$cats['RATT-S1']->id]]);

        $fs = app(FeeScheduleService::class);
        $sched = function (string $code, string $amount, string $cur, array $crit = [], ?array $inst = null) use ($fs, $cats, $year, $budget, $budget2) {
            $s = $fs->createDraft(['fee_category_id' => $cats[$code]->id, 'academic_year_id' => $year->id, 'currency' => $cur, 'amount' => $amount, 'effective_from' => '2026-09-01'] + $crit,
                $inst ?? [['label' => 'Paiement intégral', 'mode' => 'amount', 'value' => $amount, 'due_date' => '2026-12-15']], $budget);

            return $fs->approve($s, $budget2);
        };
        $three = [['label' => 'Tranche 1', 'mode' => 'percent', 'value' => '40', 'due_date' => '2026-11-15'], ['label' => 'Tranche 2', 'mode' => 'percent', 'value' => '30', 'due_date' => '2027-01-15'], ['label' => 'Tranche 3', 'mode' => 'percent', 'value' => '30', 'due_date' => '2027-04-15']];
        $sched('MINERVAL', '450', 'USD', [], $three);
        $sched('MINERVAL', '650', 'USD', ['faculty_id' => $faculties['MED']->id], $three);
        $sched('MINERVAL', '550', 'USD', ['cycle_id' => $cycleM->id], $three);
        $sched('CONNEXES', '120000', 'CDF');
        $sched('INSCR-NOUV', '30', 'USD');
        $sched('REINSCR', '20', 'USD');
        $sched('ENTERINEMENT', '80', 'USD');
        $sched('EXAM-S1', '15', 'USD');
        $sched('RATT-S1', '10', 'USD');

        $names = [['KABONGO', 'ILUNGA', 'Marie', 'F'], ['MUKENDI', 'KASONGO', 'Patrick', 'M'], ['MWAMBA', 'NGOY', 'Esther', 'F'], ['TSHIBANGU', 'MBUYI', 'Jonathan', 'M'], ['KAPINGA', 'MULUMBA', 'Grâce', 'F'],
            ['KALALA', 'BANZA', 'David', 'M'], ['NUMBI', 'KAZADI', 'Ruth', 'F'], ['LUMBALA', 'KYUNGU', 'Samuel', 'M'], ['MUJINGA', 'KITENGE', 'Rachel', 'F'], ['MPOYI', 'KALENGA', 'Joël', 'M'],
            ['NSENGA', 'MUTOMBO', 'Sarah', 'F'], ['KAYEMBE', 'TSHISEKEDI', 'Daniel', 'M'], ['BIAYI', 'MASENGO', 'Naomie', 'F'], ['KANKU', 'LUKUSA', 'Moïse', 'M'], ['MBOMBO', 'KALUME', 'Deborah', 'F'], ['ILUNGA', 'MUSAU', 'Caleb', 'M']];
        $codes = array_keys($promos);
        $charges = app(ChargeService::class);
        $students = [];
        foreach ($names as $i => [$ln, $pn, $fn, $g]) {
            $code = $codes[$i % count($codes)];
            $st = Student::query()->firstOrCreate(['matricule' => sprintf('DEMO/%04d', $i + 1)], ['last_name' => $ln.' (fictif)', 'post_name' => $pn, 'first_name' => $fn, 'gender' => $g, 'phone' => '+243 000 000 '.sprintf('%03d', $i)]);
            $status = $promos[$code]->is_final ? 'finalist' : (str_ends_with($code, 'B1') ? 'new' : 'returning');
            $e = Enrollment::query()->firstOrCreate(['student_id' => $st->id, 'academic_year_id' => $year->id], ['promotion_id' => $promos[$code]->id, 'status' => $status, 'enrolled_on' => '2026-10-05']);
            $charges->generateForEnrollment($e, $clerk);
            $students[] = $st;
        }
        if ($u = User::query()->where('username', 'etudiant')->first() ?? User::create(['name' => 'Étudiante démo (fictive)', 'username' => 'etudiant', 'password' => 'Demo!2026karo', 'student_id' => $students[0]->id])) {
            UserRoleAssignment::query()->firstOrCreate(['user_id' => $u->id, 'role_id' => Role::query()->where('slug', 'student')->value('id')]);
        }

        Storage::put('proofs/demo/bordereau-fictif.pdf', "%PDF-1.4\n% Bordereau FICTIF de démonstration\n");
        $proof = ['path' => 'proofs/demo/bordereau-fictif.pdf', 'sha256' => hash('sha256', 'demo')];
        $pay = app(PaymentService::class);
        $mkPay = function (Student $s, string $amount, BankAccount $acc, array $alloc, string $date, int $stage) use ($pay, $clerk, $validator, $budget, $year, $proof) {
            [$p] = $pay->submit(['client_uuid' => (string) Str::uuid(), 'student_id' => $s->id, 'academic_year_id' => $year->id, 'bank_account_id' => $acc->id,
                'bank_reference' => 'BQD-'.strtoupper(Str::random(6)), 'deposit_date' => $date, 'amount' => $amount, 'currency' => $acc->currency], $alloc, $proof, $clerk);
            if ($stage >= 1) {
                $pay->check($p, $validator);
            }
            if ($stage >= 2) {
                $pay->validate($p, $budget, true);
            }

            return $p;
        };
        foreach ($students as $i => $s) {
            $min = $s->charges()->whereHas('category', fn ($q) => $q->where('code', 'MINERVAL'))->first();
            $other = $s->charges()->whereHas('category', fn ($q) => $q->whereIn('code', ['INSCR-NOUV', 'REINSCR']))->first();
            $con = $s->charges()->whereHas('category', fn ($q) => $q->where('code', 'CONNEXES'))->first();
            if ($i % 4 !== 3 && $min) {
                $part = $i % 3 === 0 ? $min->amount : bcdiv_safe($min->amount);
                $alloc = [['charge_id' => $min->id, 'amount' => $part]];
                $total = $part;
                if ($other) {
                    $alloc[] = ['charge_id' => $other->id, 'amount' => $other->amount];
                    $total = Money::money(Money::add($part, $other->amount));
                }
                $mkPay($s, $i === 5 ? Money::money(Money::add($total, '25')) : $total, $usd, $alloc, '2026-10-2'.($i % 9), $i % 5 === 4 ? 1 : 2);
            }
            if ($i % 2 === 0 && $con) {
                $mkPay($s, '120000', $cdf, [['charge_id' => $con->id, 'amount' => '120000']], '2026-10-28', $i === 8 ? 0 : 2);
            }
        }
        $adj = app(AdjustmentService::class);
        $target = $students[2]->charges()->whereHas('category', fn ($q) => $q->where('code', 'MINERVAL'))->first();
        if ($target && Money::isPositive($target->balance())) {
            Storage::put('justifications/demo/bourse-fictive.pdf', "%PDF-1.4\n% Décision de bourse FICTIVE\n");
            $a = $adj->request($target, 'scholarship', '100', 'Bourse d’excellence (fictive)', $clerk, 'Fondation Démo (fictive)', 'justifications/demo/bourse-fictive.pdf');
            $adj->approve($a, $budget);
        }
        $this->command?->info('Démonstration FICTIVE prête. Mot de passe commun : Demo!2026karo — clé 2FA de démonstration : KARODEMOKAROD2FA (comptes : admin, budget, budget2, comptable, comptable.droit, controleur, recteur, sga, auditeur, etudiant).');
    }
}

function bcdiv_safe(string $amount): string
{
    return Money::money(Money::of($amount)->dividedBy(2, 2, RoundingMode::DOWN));
}
