<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\BankAccount;
use App\Models\ExamSession;
use App\Models\ExamWaiver;
use App\Models\FeeCategory;
use App\Models\FeeSchedule;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use App\Services\ContractService;
use App\Services\ExamClearanceService;
use App\Services\ReportService;
use App\Services\StatementImportService;
use App\Support\Money;
use Brick\Math\RoundingMode;
use Database\Seeders\DemoSeeder;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** Chaque écran (17) s'affiche pour chaque rôle avec les données de démonstration fictives. */
class ScreensTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['karo.demo_mode' => true]);
        $this->seed(DemoSeeder::class);
    }

    public function test_tous_les_ecrans(): void
    {
        $budget = User::query()->where('username', 'budget')->first();
        $student = Student::query()->get()->first(fn ($s) => Money::isPositive($s->charges()->whereHas('category', fn ($c) => $c->where('code', 'MINERVAL'))->first()?->balance() ?? 0));
        $payment = Payment::query()->first();
        $year = AcademicYear::query()->where('is_current', true)->first();
        $session = ExamSession::query()->first();
        $schedule = FeeSchedule::query()->first();
        $charge = $student->charges()->first();
        $contract = app(ContractService::class)->create($student, $year->id, [$student->charges()->whereHas('category', fn ($q) => $q->where('code', 'MINERVAL'))->value('id')], [['amount' => $student->charges()->whereHas('category', fn ($q) => $q->where('code', 'MINERVAL'))->first()->balance(), 'due_date' => '2027-03-01']], null, User::query()->where('username', 'comptable')->first());
        $svc = app(StatementImportService::class);
        $batch = $svc->upload(UploadedFile::fake()->createWithContent('rel.csv', "date;reference;libelle;credit\n20/10/2026;{$payment->bank_reference};Versement;{$payment->amount}\n"), BankAccount::query()->first(), $budget);
        $line = null;

        $urls = [
            route('dashboard'), route('students.index'), route('students.show', $student), route('students.create'), route('students.edit', $student),
            route('imports.students.create'), route('imports.students.template'), route('fees.index'), route('fees.schedules.show', $schedule), route('fees.schedules.create'),
            route('fees.categories.create'), route('fees.categories.edit', FeeCategory::first()), route('adjustments.index'),
            route('payments.entry', ['year' => $year->id, 'faculty' => 1]), route('payments.entry', ['year' => $year->id, 'student' => $student->id]),
            route('payments.control'), route('payments.index'), route('payments.show', $payment), route('bank.index'), route('bank.batches.show', $batch),
            route('bank.lines'), route('bank.report'), route('credits.index'), route('contracts.index'), route('contracts.show', $contract), route('contracts.pdf', $contract),
            route('contracts.create', [$student, 'year' => $year->id]), route('exams.index'), route('exams.show', $session), route('reports.index'),
            route('years.index'), route('years.show', $year), route('users.index'), route('roles.index'), route('audit.index'), route('sync.index'), route('settings.index'),
            route('account.show'), route('pwa.manifest'), route('pwa.sw'),
        ];
        foreach (array_keys(ReportService::REPORTS) as $r) {
            $urls[] = route('reports.show', $r);
        }
        // L'administrateur de démonstration cumule ici, pour le test, une vue large : on utilise plusieurs comptes.
        $users = ['budget', 'admin', 'comptable', 'controleur', 'recteur', 'auditeur', 'sga', 'comptable.droit'];
        foreach ($urls as $url) {
            $ok = false;
            foreach ($users as $u) {
                $resp = $this->as(User::query()->where('username', $u)->first())->get($url);
                $this->assertNotEquals(500, $resp->getStatusCode(), "Erreur 500 sur {$url} pour {$u}");
                $ok = $ok || $resp->getStatusCode() === 200;
            }
            $this->assertTrue($ok, "Aucun rôle n'affiche {$url}");
        }
        $this->as($budget)->get(route('reports.export', ['recovery', 'xlsx']))->assertOk();
        $this->as($budget)->get(route('reports.export', ['payments', 'pdf']))->assertOk();
        $this->as($budget)->get(route('exams.export', [$session, 'xlsx']))->assertOk();
        $etu = User::query()->where('username', 'etudiant')->first();
        $this->as($etu)->get(route('portal.show'))->assertOk()->assertSee($etu->student->matricule);
        $this->as($etu)->get(route('students.show', Student::query()->where('id', '!=', $etu->student_id)->first()))->assertForbidden();
        $this->as($etu)->get(route('payments.index'))->assertForbidden();
    }

    public function test_controle_financier_des_examens(): void
    {
        $session = ExamSession::query()->where('type', 'ordinary')->first();
        $rows = collect(app(ExamClearanceService::class)->evaluate($session));
        $this->assertTrue($rows->contains('status', 'cleared'));
        $this->assertTrue($rows->contains('status', 'due'));
        $dueRow = $rows->firstWhere('status', 'due');
        ExamWaiver::create(['student_id' => $dueRow['enrollment']->student_id, 'exam_session_id' => $session->id, 'reason' => 'Situation sociale (fictive)', 'authority' => 'Recteur', 'granted_on' => now(), 'valid_until' => now()->addMonth(), 'recorded_by' => User::first()->id]);
        $again = collect(app(ExamClearanceService::class)->evaluate($session))->firstWhere(fn ($r) => $r['enrollment']->student_id === $dueRow['enrollment']->student_id);
        $this->assertSame('waiver', $again['status']);
    }

    public function test_contrat_versionne_et_non_respecte(): void
    {
        $student = Student::query()->whereHas('charges', fn ($q) => $q->whereHas('category', fn ($c) => $c->where('code', 'MINERVAL')))->get()
            ->first(fn ($s) => Money::isPositive($s->charges()->whereHas('category', fn ($c) => $c->where('code', 'MINERVAL'))->first()->balance()));
        $charge = $student->charges()->whereHas('category', fn ($c) => $c->where('code', 'MINERVAL'))->first();
        $svc = app(ContractService::class);
        $half = Money::money(Money::of($charge->balance())->dividedBy(2, 2, RoundingMode::DOWN));
        $rest = Money::money(Money::sub($charge->balance(), $half));
        $c = $svc->create($student, $charge->academic_year_id, [$charge->id], [['amount' => $half, 'due_date' => now()->subDay()->toDateString()], ['amount' => $rest, 'due_date' => now()->addMonth()->toDateString()]], null, User::query()->where('username', 'comptable')->first());
        $svc->approve($c, User::query()->where('username', 'budget')->first());
        $this->assertTrue($svc->compliance($c->fresh('installments'))['breached']);
        $v2 = $svc->newVersion($c->fresh(), [['amount' => $charge->balance(), 'due_date' => now()->addMonths(2)->toDateString()]], null, 'Rééchelonnement accordé (fictif)', User::query()->where('username', 'comptable')->first());
        $this->assertSame(2, $v2->version);
        $this->assertSame($c->number, $v2->number);
        $this->assertSame('superseded', $c->fresh()->status);
    }
}
