<?php

namespace Tests;

use App\Models\AcademicYear;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\Cycle;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\ExamSession;
use App\Models\Faculty;
use App\Models\FeeCategory;
use App\Models\Payment;
use App\Models\Program;
use App\Models\Promotion;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use App\Models\UserRoleAssignment;
use App\Services\ChargeService;
use App\Services\FeeScheduleService;
use App\Services\PaymentService;
use App\Services\TotpService;
use App\Support\Settings;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected array $w = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
        Settings::flush();
    }

    protected function makeUser(string $role, ?int $facultyId = null, ?int $yearId = null): User
    {
        $u = User::factory()->create();
        UserRoleAssignment::create(['user_id' => $u->id, 'role_id' => Role::query()->where('slug', $role)->value('id'), 'faculty_id' => $facultyId, 'academic_year_id' => $yearId]);
        if (Role::query()->where('slug', $role)->value('requires_2fa')) {
            $u->forceFill(['two_factor_secret' => app(TotpService::class)->generateSecret(), 'two_factor_confirmed_at' => now()])->save();
        }

        return $u->fresh();
    }

    /** Connexion HTTP avec second facteur déjà validé pour la session. */
    protected function as(User $user): static
    {
        return $this->actingAs($user)->withSession(['2fa_passed' => true]);
    }

    /** Monde de test FICTIF : 2 facultés, 3 promotions, 2 années, 1 banque, barèmes approuvés. */
    protected function world(): array
    {
        if ($this->w) {
            return $this->w;
        }
        $w = [];
        $w['clerk'] = $this->makeUser('entry_clerk');
        $w['validator'] = $this->makeUser('validator');
        $w['budget'] = $this->makeUser('budget_admin');
        $w['budget2'] = $this->makeUser('budget_admin');
        $w['tech'] = $this->makeUser('tech_admin');
        $cycle = Cycle::create(['code' => 'L', 'name' => 'Licence', 'duration_years' => 3]);
        $w['fac_drt'] = Faculty::create(['code' => 'DRT', 'name' => 'Faculté de Droit (fictive)']);
        $w['fac_sci'] = Faculty::create(['code' => 'SCI', 'name' => 'Faculté des Sciences (fictive)']);
        $dDrt = Department::create(['faculty_id' => $w['fac_drt']->id, 'code' => 'DP', 'name' => 'Droit privé']);
        $dSci = Department::create(['faculty_id' => $w['fac_sci']->id, 'code' => 'INF', 'name' => 'Informatique']);
        $pDrt = Program::create(['department_id' => $dDrt->id, 'cycle_id' => $cycle->id, 'code' => 'DRT-PRIV', 'name' => 'Droit privé']);
        $pSci = Program::create(['department_id' => $dSci->id, 'cycle_id' => $cycle->id, 'code' => 'SCI-INF', 'name' => 'Informatique']);
        $w['drt_l1'] = Promotion::create(['program_id' => $pDrt->id, 'code' => 'DRT-L1', 'level' => 'Bac1', 'name' => 'Droit Bac1']);
        $w['drt_l3'] = Promotion::create(['program_id' => $pDrt->id, 'code' => 'DRT-L3', 'level' => 'Bac3', 'name' => 'Droit Bac3', 'is_final' => true]);
        $w['sci_l1'] = Promotion::create(['program_id' => $pSci->id, 'code' => 'SCI-L1', 'level' => 'Bac1', 'name' => 'Informatique Bac1']);
        $w['year'] = AcademicYear::create(['code' => '2025-2026', 'starts_on' => '2025-10-01', 'ends_on' => '2026-09-30', 'status' => 'open', 'is_current' => true]);
        $w['next'] = AcademicYear::create(['code' => '2026-2027', 'starts_on' => '2026-10-01', 'ends_on' => '2027-09-30', 'status' => 'planned', 'previous_year_id' => $w['year']->id]);
        $bank = Bank::create(['code' => 'BQX', 'name' => 'Banque Exemple (fictive)']);
        $w['acc'] = BankAccount::create(['bank_id' => $bank->id, 'account_number' => '0001-USD', 'label' => 'Frais académiques USD', 'currency' => 'USD']);
        $w['acc_cdf'] = BankAccount::create(['bank_id' => $bank->id, 'account_number' => '0002-CDF', 'label' => 'Frais CDF', 'currency' => 'CDF']);
        foreach (FeeCategory::all() as $cat) {
            $cat->bankAccounts()->sync([$w['acc']->id, $w['acc_cdf']->id]);
        }
        $w['cat'] = FeeCategory::query()->get()->keyBy('code');
        $w['s1'] = ExamSession::create(['academic_year_id' => $w['year']->id, 'semester' => 1, 'type' => 'ordinary', 'name' => 'Session S1', 'threshold_percent' => 100,
            'fee_category_ids' => [$w['cat']['MINERVAL']->id, $w['cat']['EXAM-S1']->id]]);
        $this->w = $w;
        $this->schedule('MINERVAL', '500', [['label' => 'T1', 'mode' => 'percent', 'value' => '50', 'due_date' => '2025-11-15'], ['label' => 'T2', 'mode' => 'percent', 'value' => '50', 'due_date' => '2026-02-15']]);
        $this->schedule('MINERVAL', '600', null, ['promotion_id' => $w['drt_l1']->id]);
        $this->schedule('CONNEXES', '100');
        $this->schedule('INSCR-NOUV', '50');
        $this->schedule('REINSCR', '40');
        $this->schedule('ENTERINEMENT', '120');
        $this->schedule('EXAM-S1', '20');

        return $this->w;
    }

    protected function schedule(string $code, string $amount, ?array $installments = null, array $criteria = [], string $currency = 'USD')
    {
        $w = $this->w;
        $svc = app(FeeScheduleService::class);
        $s = $svc->createDraft(array_merge(['fee_category_id' => $w['cat'][$code]->id, 'academic_year_id' => $w['year']->id, 'currency' => $currency, 'amount' => $amount, 'effective_from' => '2025-09-01'], $criteria),
            $installments ?? [['label' => 'Intégral', 'mode' => 'amount', 'value' => $amount, 'due_date' => '2025-12-31']], $w['budget']);

        return $svc->approve($s, $w['budget2']);
    }

    protected function student(Promotion $promotion, string $status = 'new', bool $charges = true): Student
    {
        $s = Student::create(['matricule' => 'TST'.Str::upper(Str::random(6)), 'last_name' => 'FICTIF', 'first_name' => 'Étudiant']);
        $e = Enrollment::create(['student_id' => $s->id, 'academic_year_id' => $this->w['year']->id, 'promotion_id' => $promotion->id, 'status' => $status, 'enrolled_on' => '2025-10-05']);
        if ($charges) {
            app(ChargeService::class)->generateForEnrollment($e, $this->w['clerk']);
        }

        return $s;
    }

    protected function charge(Student $s, string $code)
    {
        return $s->charges()->whereHas('category', fn ($q) => $q->where('code', $code))->firstOrFail();
    }

    protected function proof(): array
    {
        Storage::put('proofs/test.pdf', '%PDF-1.4 test');

        return ['path' => 'proofs/test.pdf', 'sha256' => hash('sha256', 'x')];
    }

    /** Saisie + contrôle + validation par trois personnes distinctes. */
    protected function pay(Student $s, string $amount, array $allocations, ?string $ref = null, bool $validate = true, ?BankAccount $acc = null, string $date = '2025-11-10'): Payment
    {
        $acc ??= $this->w['acc'];
        $svc = app(PaymentService::class);
        [$p] = $svc->submit(['client_uuid' => (string) Str::uuid(), 'student_id' => $s->id, 'academic_year_id' => $this->w['year']->id, 'bank_account_id' => $acc->id,
            'bank_reference' => $ref ?? 'REF'.Str::upper(Str::random(8)), 'deposit_date' => $date, 'amount' => $amount, 'currency' => $acc->currency], $allocations, $this->proof(), $this->w['clerk']);
        if ($validate) {
            $svc->check($p, $this->w['validator']);
            $svc->validate($p, $this->w['budget'], true);
        }

        return $p->fresh();
    }
}
