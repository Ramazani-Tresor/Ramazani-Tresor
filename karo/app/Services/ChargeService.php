<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Charge;
use App\Models\Enrollment;
use App\Models\ExamSession;
use App\Models\FeeCategory;
use App\Models\Student;
use App\Models\User;
use App\Support\DbErrors;
use App\Support\Money;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Établissement des créances à partir des inscriptions et des barèmes.
 * Une clé d'unicité par (inscription, frais, session) garantit qu'une génération répétée
 * ne crée jamais de doublon.
 */
class ChargeService
{
    public function __construct(private FeeScheduleService $schedules, private NumberingService $numbering) {}

    /** @return array{created:int, existing:int, missing:array<int,string>} */
    public function generateForEnrollment(Enrollment $enrollment, User $user): array
    {
        YearGuard::assertWritable($enrollment->academic_year_id);
        $result = ['created' => 0, 'existing' => 0, 'missing' => []];
        if ($enrollment->state === 'cancelled') {
            return $result;
        }
        $enrollment->loadMissing('promotion', 'academicYear');
        $categories = FeeCategory::query()->where('is_active', true)->where('trigger', 'enrollment')->orderBy('priority')->get();
        foreach ($categories as $category) {
            if (! $this->applies($category, $enrollment)) {
                continue;
            }
            $this->createFromSchedule($category, $enrollment, null, $user, $result);
        }

        return $result;
    }

    /**
     * Frais liés à une session d'examens (inscription aux examens / au rattrapage).
     * Pour le rattrapage, seuls les étudiants désignés sont concernés.
     *
     * @param  int[]|null  $studentIds  null = tous les inscrits actifs de l'année
     */
    public function generateForSession(ExamSession $session, ?array $studentIds, User $user): array
    {
        YearGuard::assertWritable($session->academic_year_id);
        $result = ['created' => 0, 'existing' => 0, 'missing' => []];
        $categories = $session->feeCategories()->where('is_active', true)->get();
        if ($categories->isEmpty()) {
            throw new BusinessException("Aucun frais n'est paramétré pour « {$session->name} » (déclencheur session, semestre {$session->semester}).");
        }
        $enrollments = Enrollment::query()->where('academic_year_id', $session->academic_year_id)->where('state', 'active')
            ->when($studentIds !== null, fn ($q) => $q->whereIn('student_id', $studentIds ?: [0]))->with('promotion')->get();
        foreach ($enrollments as $enrollment) {
            foreach ($categories as $category) {
                if ($this->applies($category, $enrollment)) {
                    $this->createFromSchedule($category, $enrollment, $session, $user, $result);
                }
            }
        }
        AuditService::log('charges.generate_session', $session, "Créances de session : {$result['created']} créée(s), {$result['existing']} déjà existante(s)");

        return $result;
    }

    /** Génération collective (année, éventuellement faculté / promotion). */
    public function generateBulk(int $yearId, ?int $facultyId, ?int $promotionId, User $user): array
    {
        YearGuard::assertWritable($yearId);
        $total = ['created' => 0, 'existing' => 0, 'missing' => []];
        Enrollment::query()->where('academic_year_id', $yearId)->where('state', 'active')
            ->when($promotionId, fn ($q) => $q->where('promotion_id', $promotionId))
            ->when($facultyId, fn ($q) => $q->whereHas('promotion.program.department', fn ($d) => $d->where('faculty_id', $facultyId)))
            ->with('promotion')->chunkById(200, function ($chunk) use (&$total, $user) {
                foreach ($chunk as $enrollment) {
                    $r = $this->generateForEnrollment($enrollment, $user);
                    $total['created'] += $r['created'];
                    $total['existing'] += $r['existing'];
                    $total['missing'] = array_values(array_unique(array_merge($total['missing'], $r['missing'])));
                }
            });
        AuditService::log('charges.generate_bulk', null, "Génération des créances : {$total['created']} créée(s), {$total['existing']} existante(s)");

        return $total;
    }

    public function applies(FeeCategory $category, Enrollment $enrollment): bool
    {
        $year = $enrollment->academicYear ?? $enrollment->academicYear()->first();
        if ($category->valid_from && $year->ends_on->lt($category->valid_from)) {
            return false;
        }
        if ($category->valid_until && $year->starts_on->gt($category->valid_until)) {
            return false;
        }
        if ($category->applicable_statuses && ! in_array($enrollment->status, $category->applicable_statuses, true)) {
            return false;
        }
        if ($category->final_promotions_only && ! $enrollment->promotion->is_final) {
            return false;
        }

        return true;
    }

    private function createFromSchedule(FeeCategory $category, Enrollment $enrollment, ?ExamSession $session, User $user, array &$result): void
    {
        $key = 'E'.$enrollment->id.'-C'.$category->id.'-S'.($session?->id ?? 0);
        if (Charge::query()->where('uniqueness_key', $key)->exists()) {
            $result['existing']++;

            return;
        }
        $schedule = $this->schedules->resolve($category, $enrollment);
        if (! $schedule) {
            $result['missing'][] = $category->label.' ('.$enrollment->promotion->code.')';

            return;
        }
        try {
            DB::transaction(function () use ($category, $enrollment, $session, $user, $schedule, $key) {
                $charge = Charge::create([
                    'number' => $this->numbering->next('charge'),
                    'student_id' => $enrollment->student_id,
                    'academic_year_id' => $enrollment->academic_year_id,
                    'enrollment_id' => $enrollment->id,
                    'fee_category_id' => $category->id,
                    'fee_schedule_id' => $schedule->id,
                    'exam_session_id' => $session?->id,
                    'currency' => $schedule->currency,
                    'amount' => $schedule->amount,
                    'origin' => 'initial',
                    'status' => 'active',
                    'uniqueness_key' => $key,
                    'created_by' => $user->id,
                ]);
                foreach ($schedule->installments as $inst) {
                    $charge->installments()->create(['sequence' => $inst->sequence, 'label' => $inst->label, 'amount' => $inst->amount, 'due_date' => $inst->due_date]);
                }
            });
            $result['created']++;
        } catch (QueryException $e) {
            if (! DbErrors::isUniqueViolation($e)) {
                throw $e;
            }
            $result['existing']++; // génération concurrente : la créance existe déjà
        }
    }

    /** Créance manuelle pour un frais « sur décision » (autres frais créés par les personnes habilitées). */
    public function createManual(Student $student, int $yearId, FeeCategory $category, string $amount, string $currency, string $dueDate, string $reason, User $user, ?string $clientUuid = null): Charge
    {
        YearGuard::assertWritable($yearId);
        if (! Money::isPositive($amount)) {
            throw new BusinessException('Le montant doit être positif.');
        }
        if (trim($reason) === '') {
            throw new BusinessException('Le motif est obligatoire.');
        }
        $key = 'M-'.($clientUuid ?: (string) Str::uuid());
        if ($existing = Charge::query()->where('uniqueness_key', $key)->first()) {
            return $existing;
        }

        return DB::transaction(function () use ($student, $yearId, $category, $amount, $currency, $dueDate, $reason, $user, $key) {
            $charge = Charge::create([
                'number' => $this->numbering->next('charge'),
                'student_id' => $student->id,
                'academic_year_id' => $yearId,
                'enrollment_id' => $student->enrollmentFor($yearId)?->id,
                'fee_category_id' => $category->id,
                'currency' => $currency,
                'amount' => Money::money($amount),
                'origin' => 'manual',
                'status' => 'active',
                'uniqueness_key' => $key,
                'notes' => $reason,
                'created_by' => $user->id,
            ]);
            $charge->installments()->create(['sequence' => 1, 'label' => 'Paiement intégral', 'amount' => $charge->amount, 'due_date' => $dueDate]);
            AuditService::log('charge.manual', $charge, "Créance manuelle {$charge->number} : ".Money::format($amount, $currency), null, null, $reason);

            return $charge;
        });
    }

    public function cancel(Charge $charge, string $reason, User $user): Charge
    {
        return DB::transaction(function () use ($charge, $reason, $user) {
            $charge = Charge::query()->lockForUpdate()->findOrFail($charge->id);
            YearGuard::assertWritable($charge->academic_year_id);
            if ($charge->allocations()->whereIn('status', ['proposed', 'active'])->exists()) {
                throw new BusinessException('Cette créance porte des affectations : annulez-les (contrepassation) avant de l’annuler.');
            }
            if (trim($reason) === '') {
                throw new BusinessException('Le motif est obligatoire.');
            }
            $charge->update(['status' => 'cancelled', 'notes' => trim($charge->notes.' | Annulée : '.$reason, ' |')]);
            AuditService::log('charge.cancel', $charge, "Annulation de la créance {$charge->number}", null, null, $reason, $user->id);

            return $charge;
        });
    }
}
