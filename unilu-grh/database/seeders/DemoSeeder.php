<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\AssetCategory;
use App\Models\Grade;
use App\Models\JobFunction;
use App\Models\KinshipDegree;
use App\Models\Mandate;
use App\Models\OrgUnit;
use App\Models\Position;
use App\Models\PrimeEntitlement;
use App\Models\PrimeRate;
use App\Models\PrimeType;
use App\Models\Qualification;
use App\Models\Role;
use App\Models\SituationType;
use App\Models\SupportType;
use App\Models\User;
use App\Models\UserRoleAssignment;
use App\Services\AssetService;
use App\Services\AssignmentService;
use App\Services\CareerService;
use App\Services\DeathService;
use App\Services\EmployeeService;
use App\Services\MutationService;
use App\Services\PrimeService;
use App\Services\RecruitmentService;
use App\Services\SituationService;
use App\Services\SocialSupportService;
use App\Support\Money;
use App\Support\Settings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * DONNÉES DE DÉMONSTRATION ENTIÈREMENT FICTIVES, à charger UNIQUEMENT dans une base dédiée.
 * Refus si GRH_DEMO n'est pas à true ou en production. Matricules préfixés DEMO ; montants arbitraires d'illustration.
 */
class DemoSeeder extends Seeder
{
    public const PASSWORD = 'Demo!2026grh';

    public function run(): void
    {
        if (! config('grh.demo_mode') || app()->environment('production')) {
            $this->command?->error('Refusé : définissez GRH_DEMO=true sur une base de démonstration dédiée (jamais en production).');

            return;
        }
        $this->call(DatabaseSeeder::class);
        Settings::set(['university_name' => 'Université de Likasi (démonstration)', 'university_acronym' => 'UNILU', 'signatory_1_name' => 'M. A. Démo (fictif)', 'signatory_2_name' => 'Pr B. Démo (fictif)', 'demo_mode' => '1']);
        mt_srand(2026);

        foreach ([['2024-2025', '2024-10-01', '2025-09-30', 'closed'], ['2025-2026', '2025-10-01', '2026-09-30', 'closed'], ['2026-2027', '2026-10-01', '2027-09-30', 'open']] as [$c, $s, $e, $st]) {
            AcademicYear::query()->firstOrCreate(['code' => $c], ['starts_on' => $s, 'ends_on' => $e, 'status' => $st, 'is_current' => $c === '2026-2027']);
        }

        // Structure
        $rect = OrgUnit::query()->where('code', 'RECT')->first();
        $u = fn ($code, $name, $type, $parent) => OrgUnit::query()->firstOrCreate(['code' => $code], ['name' => $name, 'type' => $type, 'parent_id' => $parent?->id]);
        $vra = $u('VRA', 'Vice-rectorat chargé des affaires académiques', 'vice_rectorate', $rect);
        $sga = $u('SGAC', 'Secrétariat général académique', 'sga', $rect);
        $sgad = $u('SGADM', 'Secrétariat général administratif', 'sgad', $rect);
        $fsci = $u('FSCI', 'Faculté des Sciences', 'faculty', $vra);
        $chim = $u('FSCI-CHIM', 'Département de Chimie', 'department', $fsci);
        $phys = $u('FSCI-PHYS', 'Département de Physique', 'department', $fsci);
        $fdr = $u('FDROIT', 'Faculté de Droit', 'faculty', $vra);
        $dpriv = $u('FDROIT-PRIV', 'Département de Droit privé', 'department', $fdr);
        $fmed = $u('FMED', 'Faculté de Médecine', 'faculty', $vra);
        $fpoly = $u('FPOLY', 'Faculté Polytechnique', 'faculty', $vra);
        $mines = $u('FPOLY-MIN', 'Département des Mines', 'department', $fpoly);
        $bib = $u('BIB', 'Bibliothèque centrale', 'library', $sga);
        $crech = $u('CRMIN', 'Centre de recherche en géologie minière', 'research_center', $fpoly);
        $spers = $u('SPERS', 'Service du personnel', 'admin_service', $sgad);
        $sfin = $u('SFIN', 'Service financier', 'admin_service', $sgad);
        $stech = $u('STECH', 'Service technique et maintenance', 'technical_service', $sgad);
        OrgUnit::flushTree();
        $fn = fn ($code) => JobFunction::query()->where('code', $code)->value('id');
        foreach ([[$fsci, 'DOY-FSCI', 'Doyen de la Faculté des Sciences', 'DOY', 1], [$fsci, 'SEC-FSCI', 'Secrétaire de faculté', 'SECFAC', 1], [$chim, 'CDEP-CHIM', 'Chef du département de Chimie', 'CDEP', 1], [$chim, 'ENS-CHIM', 'Enseignant-chercheur en chimie', 'ENS', 8],
            [$fdr, 'DOY-FDR', 'Doyen de la Faculté de Droit', 'DOY', 1], [$dpriv, 'ENS-DPRIV', 'Enseignant en droit privé', 'ENS', 6], [$fmed, 'DOY-FMED', 'Doyen de la Faculté de Médecine', 'DOY', 1], [$fmed, 'ENS-FMED', 'Enseignant en médecine', 'ENS', 6],
            [$spers, 'CS-PERS', 'Chef du service du personnel', 'CSERV', 1], [$spers, 'AG-PERS', 'Agent administratif du personnel', 'AGADM', 4], [$sfin, 'AG-FIN', 'Agent financier', 'AGADM', 3], [$stech, 'TECH-1', 'Technicien de maintenance', 'TECHF', 4], [$stech, 'OUV-1', 'Ouvrier', 'OUVF', 5], [$bib, 'BIBL-1', 'Bibliothécaire', 'BIBL', 2], [$mines, 'ENS-MIN', 'Enseignant en génie minier', 'ENS', 6]] as [$unit, $code, $title, $f, $seats]) {
            Position::query()->firstOrCreate(['code' => $code], ['org_unit_id' => $unit->id, 'title' => $title, 'job_function_id' => $fn($f), 'seats' => $seats]);
        }

        // Comptes de démonstration
        $mk = function (string $username, string $name, string $role, ?int $unit = null, ?string $family = null) {
            $usr = User::query()->firstOrCreate(['username' => $username], ['name' => $name.' (fictif)', 'email' => $username.'@demo.invalid', 'password' => self::PASSWORD]);
            UserRoleAssignment::query()->firstOrCreate(['user_id' => $usr->id, 'role_id' => Role::query()->where('slug', $role)->value('id'), 'org_unit_id' => $unit, 'staff_family' => $family]);

            return $usr->fresh();
        };
        User::query()->where('username', 'admin')->first()?->forceFill(['password' => self::PASSWORD, 'must_change_password' => false])->save();
        $hr = $mk('rh', 'Administratrice RH', 'hr_admin');
        $hr2 = $mk('rh2', 'Adjoint RH (validateur)', 'hr_admin');
        $clerk = $mk('gestionnaire', 'Gestionnaire du personnel', 'personnel_manager');
        $mk('acad', 'Responsable du personnel académique', 'academic_officer', null, 'academic');
        $mk('scient', 'Responsable du personnel scientifique', 'scientific_officer', null, 'scientific');
        $fin = $mk('finance', 'Gestionnaire financier', 'finance_manager');
        $fin2 = $mk('finance2', 'Contrôleur financier', 'finance_manager');
        $asset = $mk('patrimoine', 'Responsable du patrimoine', 'asset_manager');
        $mk('doyen.sciences', 'Doyen de la Faculté des Sciences', 'faculty_head', $fsci->id);
        $mk('chef.personnel', 'Chef du service du personnel', 'service_head', $spers->id);

        // Agents
        $es = app(EmployeeService::class);
        $as = app(AssignmentService::class);
        $g = fn ($code) => Grade::query()->where('code', $code)->first();
        $last = ['MWAMBA', 'ILUNGA', 'KASONGO', 'MBUYI', 'TSHIBANGU', 'NGOY', 'KALENGA', 'MUKENDI', 'BANZA', 'KYUNGU', 'KAPINGA', 'NDAY', 'MUSONDA', 'LUMBU', 'KAYEMBE', 'MPANGA', 'KITENGE', 'MULONGO', 'NUMBI', 'SANGWA'];
        $middle = ['KABWE', 'MUTEBA', 'KALUMBA', 'MONGA', 'KAZADI', 'MASENGO', 'KILUFYA', 'MUJINGA', 'KABANGE', 'LUBOYA'];
        $first = ['Albert', 'Béatrice', 'Christian', 'Delphine', 'Emmanuel', 'Françoise', 'Gaston', 'Hélène', 'Isaac', 'Joséphine', 'Kevin', 'Laure', 'Marcel', 'Nadine', 'Olivier', 'Pascaline', 'Robert', 'Sarah', 'Thierry', 'Véronique'];
        $people = [
            // [grade, unité, poste, engagement, extra]
            ['PO', $chim, 'CDEP-CHIM', '2004-10-01'], ['PF', $chim, 'ENS-CHIM', '2008-10-01'], ['PA', $phys, null, '2014-10-01'], ['PE', $fsci, null, '1992-10-01'], ['PO', $fsci, 'DOY-FSCI', '2001-10-01'],
            ['PO', $fdr, 'DOY-FDR', '2003-10-01'], ['PA', $dpriv, 'ENS-DPRIV', '2016-10-01'], ['PF', $fmed, 'DOY-FMED', '2006-10-01'], ['PA', $fmed, 'ENS-FMED', '2017-10-01'], ['PA', $mines, 'ENS-MIN', '2015-10-01'],
            ['CT', $chim, 'ENS-CHIM', '2012-10-01'], ['CT', $mines, 'ENS-MIN', '2013-10-01'], ['CT', $dpriv, 'ENS-DPRIV', '2014-10-01'], ['CT', $crech, null, '2015-10-01'],
            ['ASS', $chim, 'ENS-CHIM', '2020-10-01', ['assistant_track' => 'teaching']], ['ASS', $phys, null, '2021-10-01', ['assistant_track' => 'teaching']], ['ASS', $crech, null, '2019-10-01', ['assistant_track' => 'research', 'admin_grade_id' => $g('CB')->id]],
            ['ASS', $dpriv, 'ENS-DPRIV', '2022-10-01', ['assistant_track' => 'teaching']], ['ASS', $fmed, 'ENS-FMED', '2023-10-01', ['assistant_track' => 'teaching']], ['ASS', $mines, 'ENS-MIN', '2018-10-01', ['assistant_track' => 'research', 'admin_grade_id' => $g('AB1')->id]],
            ['CB', $spers, 'CS-PERS', '2005-03-01'], ['AB1', $spers, 'AG-PERS', '2010-06-01'], ['AB2', $spers, 'AG-PERS', '2015-01-15'], ['CD', $sfin, null, '2002-02-01'], ['AB1', $sfin, 'AG-FIN', '2011-09-01'],
            ['AGB1', $sfin, 'AG-FIN', '2018-04-01'], ['TECHN', $stech, 'TECH-1', '2009-07-01'], ['TECHN', $stech, 'TECH-1', '2016-07-01'], ['AA', $stech, 'OUV-1', '2012-01-01'], ['HUI', $stech, 'OUV-1', '2019-05-01'],
            ['AB2', $bib, 'BIBL-1', '2014-02-01'], ['DIR', $sgad, null, '1999-01-01'], ['AGB2', $stech, 'OUV-1', '2021-03-01'], ['ASS', $chim, 'ENS-CHIM', '2024-10-01', ['assistant_track' => 'teaching']],
        ];
        $emps = [];
        foreach ($people as $i => $p) {
            [$code, $unit, $pos, $eng] = $p;
            $grade = $g($code);
            $extra = $p[4] ?? [];
            $sex = $i % 3 === 1 ? 'F' : 'M';
            $e = $es->create($extra + [
                'matricule' => 'DEMO-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT), 'last_name' => $last[$i % 20], 'middle_name' => $middle[$i % 10], 'first_name' => $first[($i * 7) % 20],
                'sex' => $sex, 'birth_date' => Carbon::parse($eng)->subYears(27 + ($i % 9))->toDateString(), 'birth_place' => ['Likasi', 'Lubumbashi', 'Kolwezi', 'Kipushi'][$i % 4],
                'marital_status' => ['married', 'single', 'married', 'widowed'][$i % 4], 'children_count' => $i % 5, 'phone' => '+243 99'.str_pad((string) (1000000 + $i * 7919 % 9000000), 7, '0'),
                'email' => 'agent'.($i + 1).'@demo.invalid', 'address' => 'Avenue fictive n° '.($i + 3).', Likasi', 'emergency_contact_name' => 'Contact fictif '.($i + 1), 'emergency_contact_phone' => '+243 81 000 00'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'staff_family' => $grade->family, 'staff_category_id' => $grade->staff_category_id, 'grade_id' => $grade->id,
                'first_service_date' => $eng, 'engagement_date' => $eng,
            ], $hr, 'Reprise des données (démonstration)');
            $as->assign($e, ['org_unit_id' => $unit->id, 'position_id' => $pos ? Position::query()->where('code', $pos)->value('id') : null, 'start_date' => $eng, 'reason' => 'Affectation initiale (reprise)', 'decision_number' => 'DEMO/AFF/'.($i + 1)], $hr, true, false);
            $emps[$i] = $e->fresh();
        }
        // Compte agent lié (espace personnel)
        $agent = $mk('agent', 'Agent du personnel', 'agent');
        $agent->update(['employee_id' => $emps[14]->id]);

        // Mandats d'assistant et carrière
        $cs = app(CareerService::class);
        $m1 = Mandate::query()->where('code', 'ASS_M1')->first();
        $m2 = Mandate::query()->where('code', 'ASS_M2')->first();
        foreach ([14 => [$m1, '2024-10-01', now()->addDays(45)], 15 => [$m2, '2023-10-01', now()->addMonths(10)], 16 => [$m2, '2022-10-01', now()->addDays(20)], 17 => [$m1, '2024-10-01', now()->addMonths(14)], 18 => [$m1, '2023-10-01', now()->subDays(10)], 19 => [$m2, '2022-10-01', now()->addMonths(5)], 33 => [$m1, '2024-10-01', now()->addMonths(22)]] as $i => [$m, $from, $to]) {
            $ev = $cs->propose($emps[$i], ['type' => 'mandate_renewal', 'new_mandate_id' => $m->id, 'new_mandate_end' => $to->toDateString(), 'effective_date' => $from, 'decision_date' => $from, 'decision_number' => 'DEMO/REC/MAND/'.$i, 'signing_authority' => 'Le Recteur'], $clerk);
            $cs->validate($ev, $hr);
        }
        $ev = $cs->propose($emps[10], ['type' => 'promotion', 'new_grade_id' => $g('PA')->id, 'allow_family_change' => true, 'effective_date' => '2025-10-01', 'decision_date' => '2025-09-15', 'decision_number' => 'DEMO/CA/2025/017', 'signing_authority' => 'Conseil d’administration', 'observations' => 'Après soutenance de thèse'], $clerk);
        $cs->validate($ev, $hr);
        $ev = $cs->propose($emps[2], ['type' => 'promotion', 'new_grade_id' => $g('PF')->id, 'effective_date' => '2026-10-01', 'decision_date' => '2026-09-20', 'decision_number' => 'DEMO/CA/2026/004', 'signing_authority' => 'Conseil d’administration'], $clerk);
        $cs->validate($ev, $hr2);
        // Propositions en attente de validation
        $cs->propose($emps[11], ['type' => 'promotion', 'new_grade_id' => $g('PA')->id, 'allow_family_change' => true, 'effective_date' => '2027-01-01', 'decision_date' => now()->toDateString(), 'decision_number' => 'DEMO/CA/2026/031', 'signing_authority' => 'Conseil d’administration'], $clerk);
        $cs->propose($emps[22], ['type' => 'promotion', 'new_grade_id' => $g('AB1')->id, 'effective_date' => '2027-01-01', 'decision_date' => now()->toDateString(), 'decision_number' => 'DEMO/SGAD/2026/112', 'signing_authority' => 'Le Secrétaire général administratif'], $clerk);

        // Diplômes et production scientifique
        $y = fn ($c) => AcademicYear::query()->where('code', $c)->value('id');
        $q = function ($i, $nature, $year, $title, $unit = null, $date = null) use ($emps, $y) {
            Qualification::create(['employee_id' => $emps[$i]->id, 'nature' => $nature, 'title' => $title, 'field' => ['Chimie', 'Physique', 'Droit', 'Médecine', 'Génie minier'][$i % 5], 'institution' => 'Université de Likasi (fictif)', 'defense_university' => $i % 3 ? 'Université de Lubumbashi (fictif)' : 'Université de Likasi (fictif)', 'defense_date' => $date, 'academic_year_id' => $year ? $y($year) : null, 'mention' => ['Distinction', 'Grande distinction', 'Satisfaction'][$i % 3], 'supervisor' => 'Pr Directeur fictif '.($i % 4 + 1), 'org_unit_id' => $unit?->id ?? $emps[$i]->currentUnitId()]);
        };
        foreach ([0, 1, 2, 3, 4, 5, 6, 7, 8, 9] as $i) {
            $q($i, 'licence', null, 'Licence', null, '19'.(85 + $i).'-07-15');
            $q($i, 'dea', null, 'DEA de recherche', null, '20'.str_pad((string) ($i % 10), 2, '0', STR_PAD_LEFT).'-07-20');
            $q($i, 'doctoral_thesis', $i < 5 ? null : ($i % 2 ? '2024-2025' : '2025-2026'), 'Thèse fictive n° '.($i + 1), null, ($i < 5 ? '2005' : ($i % 2 ? '2025' : '2026')).'-0'.(3 + $i % 6).'-12');
            $q($i, 'doctorate', null, 'Docteur', null, null);
        }
        foreach ([10, 11, 12, 13] as $i) {
            $q($i, 'dea', $i % 2 ? '2024-2025' : '2025-2026', 'DEA', null, ($i % 2 ? '2025' : '2026').'-06-30');
            $q($i, 'dea_thesis', $i % 2 ? '2024-2025' : '2025-2026', 'Mémoire de DEA fictif', null, ($i % 2 ? '2025' : '2026').'-06-30');
        }
        $q(10, 'doctoral_thesis', '2024-2025', 'Thèse fictive de chimie analytique', $chim, '2025-06-21');
        $q(10, 'doctorate', '2024-2025', 'Docteur en sciences', $chim, '2025-06-21');
        $q(11, 'doctoral_thesis', '2025-2026', 'Thèse fictive de géologie minière', $mines, '2026-07-02');
        $q(11, 'doctorate', '2025-2026', 'Docteur en sciences de l’ingénieur', $mines, '2026-07-02');
        foreach ([14, 15, 16, 17, 18, 19, 33] as $i) {
            $q($i, 'research_thesis', '2025-2026', 'Mémoire de recherche fictif', null, '2026-05-10');
        }

        // Situations administratives
        $ss = app(SituationService::class);
        $st = fn ($c) => SituationType::query()->where('code', $c)->value('id');
        $s = $ss->request($emps[21], ['situation_type_id' => $st('CONGE_ANNUEL'), 'start_date' => now()->subDays(5)->toDateString(), 'planned_end_date' => now()->addDays(4)->toDateString(), 'reason' => 'Congé annuel', 'decision_reference' => 'DEMO/CONGE/01'], $clerk);
        $ss->approve($s, $hr);
        $s = $ss->request($emps[27], ['situation_type_id' => $st('CONGE_MALADIE'), 'start_date' => now()->subDays(12)->toDateString(), 'planned_end_date' => now()->addDays(18)->toDateString(), 'reason' => 'Certificat médical (fictif)'], $clerk);
        $ss->approve($s, $hr);
        $s = $ss->request($emps[28], ['situation_type_id' => $st('SUSP_ADMIN'), 'start_date' => '2026-10-01', 'planned_end_date' => now()->addDays(20)->toDateString(), 'reason' => 'Enquête administrative (fictive)', 'decision_reference' => 'DEMO/SGAD/SUSP/03'], $clerk);
        $ss->approve($s, $hr);
        $ss->request($emps[12], ['situation_type_id' => $st('DISPONIBILITE'), 'start_date' => now()->addDays(20)->toDateString(), 'planned_end_date' => now()->addMonths(12)->toDateString(), 'reason' => 'Convenances personnelles (fictif)'], $clerk);
        $ss->request($emps[23], ['situation_type_id' => $st('CONGE_ANNUEL'), 'start_date' => now()->addDays(30)->toDateString(), 'planned_end_date' => now()->addDays(52)->toDateString(), 'reason' => 'Congé annuel'], $clerk);
        $s = $ss->request($emps[31], ['situation_type_id' => $st('RETRAITE'), 'start_date' => '2026-09-01', 'reason' => 'Admission à la retraite', 'decision_reference' => 'DEMO/MIN/RET/2026/12'], $clerk);
        $ss->approve($s, $hr);

        // Taux de primes (montants d'illustration) et génération
        $type = PrimeType::query()->where('code', 'PRIME_MENS')->first();
        foreach ([['academic', null, '1200.00'], ['scientific', null, '450.00'], ['administrative', null, '220.00'], [null, $g('PO')->id, '1500.00'], [null, $g('PE')->id, '1350.00'], [null, $g('CT')->id, '650.00']] as [$fam, $grade, $amount]) {
            PrimeRate::query()->firstOrCreate(['prime_type_id' => $type->id, 'staff_family' => $fam, 'grade_id' => $grade], ['amount' => $amount, 'currency' => 'USD']);
        }
        PrimeRate::query()->firstOrCreate(['prime_type_id' => $type->id, 'job_function_id' => $fn('DOY')], ['amount' => '1700.00', 'currency' => 'USD']);
        $ps = app(PrimeService::class);
        foreach (['2026-08', '2026-09', '2026-10'] as $month) {
            $start = Carbon::parse($month.'-01');
            $ps->generate($type, $start, $start->copy()->endOfMonth(), $month, $fin);
        }

        // Mutations, décès (avant paiements pour illustrer les recalculs)
        $ms = app(MutationService::class);
        $m = $ms->propose($emps[25], ['kind' => 'external', 'destination_institution' => 'Université partenaire (fictive)', 'reason' => 'Demande de l’agent', 'decision_date' => '2026-09-25', 'effective_date' => '2026-10-16', 'decision_reference' => 'DEMO/MUT/2026/07'], $clerk);
        $ms->validate($m, $hr);
        $m = $ms->propose($emps[6], ['kind' => 'internal', 'to_unit_id' => $fdr->id, 'reason' => 'Nécessité de service', 'decision_date' => now()->toDateString(), 'effective_date' => now()->addDays(10)->toDateString(), 'decision_reference' => 'DEMO/MUT/2026/09'], $clerk);
        KinshipDegree::query()->where('code', 'AGENT')->update(['support_amount' => '1000.00']);
        KinshipDegree::query()->where('code', 'CONJOINT')->update(['support_amount' => '500.00']);
        KinshipDegree::query()->where('code', 'ENFANT')->update(['support_amount' => '400.00']);
        KinshipDegree::query()->whereIn('code', ['PERE', 'MERE'])->update(['support_amount' => '300.00']);
        KinshipDegree::query()->where('code', 'AUTRE')->update(['support_amount' => '150.00']);
        SupportType::query()->where('code', 'MALADIE')->update(['default_amount' => '200.00', 'max_amount' => '800.00']);

        // Paiements : août et septembre payés (avec quelques acomptes), octobre partiellement
        $ents = PrimeEntitlement::query()->with(['adjustments', 'payments', 'employee'])->orderBy('id')->get();
        foreach ($ents as $k => $ent) {
            if (! Money::isPositive($ent->balance())) {
                continue;
            }
            if ($ent->period_label === '2026-10' && $k % 3 !== 0) {
                if ($k % 3 === 1) {
                    $p = $ps->record($ent, 'advance', Money::money(Money::mul($ent->balance(), '0.4')), ['payment_date' => '2026-10-20', 'mode' => 'cash', 'reference' => 'DEMO-ACP-'.$k], $fin);
                    $ps->validate($p, $fin2);
                }

                continue;
            }
            $p = $ps->record($ent->fresh(['adjustments', 'payments', 'employee']), 'payment', null, ['payment_date' => $ent->period_end->copy()->addDays(5)->min(now())->toDateString(), 'mode' => 'bank_transfer', 'reference' => 'DEMO-VIR-'.$ent->period_label.'-'.$k, 'beneficiary' => $ent->employee->status === 'deceased' ? 'Ayant droit fictif' : null], $fin);
            if ($k % 11 !== 5) {
                $ps->validate($p, $fin2);
            }
        }
        // Décès validé après paiement de septembre : arrêt des primes et trop-perçu éventuel sur octobre
        $ds = app(DeathService::class);
        $d = $ds->record($emps[29], ['death_date' => '2026-10-12', 'death_place' => 'Likasi', 'contact_name' => 'Conjoint (fictif)', 'contact_phone' => '+243 81 111 11 11', 'contact_relation' => 'Conjoint'], $clerk);
        $ds->validate($d, $hr);

        // Soutiens sociaux
        $sup = app(SocialSupportService::class);
        $deuil = SupportType::query()->where('code', 'DEUIL')->first();
        $s1 = $sup->request($emps[22], ['support_type_id' => $deuil->id, 'kinship_degree_id' => KinshipDegree::query()->where('code', 'PERE')->value('id'), 'deceased_name' => 'Père de l’agent (fictif)', 'event_date' => '2026-09-14'], $clerk);
        $sup->approve($s1, null, 'DEMO/SOC/2026/21', $hr);
        $sup->pay($s1->fresh(), '2026-09-20', 'DEMO-VIR-SOC-21', $fin);
        $s2 = $sup->request($emps[8], ['support_type_id' => $deuil->id, 'kinship_degree_id' => KinshipDegree::query()->where('code', 'ENFANT')->value('id'), 'deceased_name' => 'Enfant de l’agent (fictif)', 'event_date' => '2026-10-30'], $clerk);
        $sup->approve($s2, null, 'DEMO/SOC/2026/27', $hr);
        $sup->request($emps[27], ['support_type_id' => SupportType::query()->where('code', 'MALADIE')->value('id'), 'description' => 'Hospitalisation (fictive)'], $clerk);

        // Recrutement
        $rs = app(RecruitmentService::class);
        $c1 = $rs->create(['last_name' => 'KAHILU', 'middle_name' => 'MBAYO', 'first_name' => 'Joël (fictif)', 'sex' => 'M', 'staff_family' => 'scientific', 'grade_id' => $g('ASS')->id, 'org_unit_id' => $phys->id, 'highest_degree' => 'Licence en physique'], $clerk);
        $rs->transition($c1, 'under_review', $clerk);
        $c2 = $rs->create(['last_name' => 'MUSAU', 'middle_name' => 'KASEYA', 'first_name' => 'Grâce (fictive)', 'sex' => 'F', 'staff_family' => 'administrative', 'grade_id' => $g('AGB2')->id, 'org_unit_id' => $sfin->id, 'highest_degree' => 'Graduat en comptabilité'], $clerk);
        $rs->transition($c2, 'under_review', $clerk);
        $rs->transition($c2->fresh(), 'validated', $hr);
        $rs->create(['last_name' => 'KYALA', 'first_name' => 'Patrick (fictif)', 'sex' => 'M', 'staff_family' => 'academic', 'grade_id' => $g('PA')->id, 'org_unit_id' => $fmed->id, 'highest_degree' => 'Doctorat en médecine'], $clerk);

        // Patrimoine
        $ast = app(AssetService::class);
        $cat = fn ($c) => AssetCategory::query()->where('code', $c)->value('id');
        $batSci = $ast->create(['asset_category_id' => $cat('BAT'), 'code' => 'DEMO-BAT-001', 'designation' => 'Bâtiment de la Faculté des Sciences', 'location' => 'Campus principal', 'acquisition_date' => '1998-09-01', 'acquisition_mode' => 'construction', 'value' => '850000', 'condition' => 'fair', 'org_unit_id' => $fsci->id], $asset);
        $salle = $ast->create(['asset_category_id' => $cat('SALLE'), 'code' => 'DEMO-SAL-012', 'designation' => 'Salle de cours 12 (120 places)', 'parent_id' => $batSci->id, 'condition' => 'good', 'org_unit_id' => $fsci->id], $asset);
        $ast->create(['asset_category_id' => $cat('LABO'), 'code' => 'DEMO-LAB-003', 'designation' => 'Laboratoire de chimie analytique', 'parent_id' => $batSci->id, 'condition' => 'good', 'org_unit_id' => $chim->id, 'responsible_employee_id' => $emps[0]->id], $asset);
        $ast->create(['asset_category_id' => $cat('RES'), 'code' => 'DEMO-RES-001', 'designation' => 'Résidence universitaire Bloc A', 'location' => 'Cité universitaire', 'acquisition_date' => '2010-01-15', 'acquisition_mode' => 'construction', 'value' => '420000', 'condition' => 'poor', 'org_unit_id' => $sgad->id], $asset);
        $ast->create(['asset_category_id' => $cat('BANC'), 'code' => 'DEMO-BNC-012', 'designation' => 'Bancs d’amphithéâtre', 'parent_id' => $salle->id, 'quantity' => 60, 'condition' => 'fair', 'org_unit_id' => $fsci->id], $asset);
        for ($i = 1; $i <= 6; $i++) {
            $pc = $ast->create(['asset_category_id' => $cat('ORDI'), 'code' => 'DEMO-ORD-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT), 'designation' => 'Ordinateur de bureau', 'brand_model' => 'Marque fictive X'.$i, 'serial_number' => 'SN-DEMO-'.$i, 'acquisition_date' => '2024-0'.$i.'-10', 'acquisition_mode' => 'purchase', 'value' => '780', 'condition' => 'new', 'org_unit_id' => $sfin->id], $asset);
            if ($i <= 2) {
                $ast->transfer($pc, ['to_unit_id' => $spers->id, 'responsible_employee_id' => $emps[20 + $i]->id, 'movement_date' => '2025-02-01', 'reference' => 'DEMO-BS-'.$i], $asset);
            }
        }
        $ast->create(['asset_category_id' => $cat('IMPR'), 'code' => 'DEMO-IMP-001', 'designation' => 'Imprimante laser réseau', 'condition' => 'good', 'org_unit_id' => $spers->id], $asset);
        $veh = $ast->create(['asset_category_id' => $cat('VEH'), 'code' => 'DEMO-VEH-001', 'designation' => 'Véhicule de liaison 4x4', 'serial_number' => 'DEMO-1234-AB', 'acquisition_date' => '2015-05-05', 'acquisition_mode' => 'purchase', 'value' => '38000', 'condition' => 'poor', 'org_unit_id' => $stech->id, 'responsible_employee_id' => $emps[26]->id], $asset);
        $ast->changeCondition($veh, 'out_of_order', $asset, 'Moteur hors service');
        $ast->retire($veh->fresh(), 'reform', 'DEMO/PV/REF/2026/02', $asset, 'Réparation non rentable');
        $ast->create(['asset_category_id' => $cat('MOTO'), 'code' => 'DEMO-MOT-001', 'designation' => 'Moto de service', 'condition' => 'good', 'org_unit_id' => $stech->id], $asset);
        $inv = $ast->openInventory(['label' => 'Inventaire annuel 2026 — Faculté des Sciences', 'org_unit_id' => $fsci->id, 'started_on' => now()->subDays(3)->toDateString()], $asset);
        $ast->check($inv, $salle->fresh(), true, 'good', null, null, $asset);

        $this->command?->info('Démonstration chargée (données fictives). Mot de passe des comptes : '.self::PASSWORD);
    }
}
