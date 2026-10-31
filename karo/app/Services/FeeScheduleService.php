<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Charge;
use App\Models\ChargeAdjustment;
use App\Models\Enrollment;
use App\Models\FeeCategory;
use App\Models\FeeSchedule;
use App\Models\User;
use App\Support\Authz;
use App\Support\Money;
use App\Support\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Barèmes versionnés.
 *  - Un barème est préparé en brouillon (fees.manage) puis approuvé (fees.approve) par une autre personne.
 *  - Critères facultatifs : faculté, filière, promotion, cycle, statut. Le barème applicable est le plus
 *    spécifique (nombre de critères renseignés) ; deux barèmes de même spécificité qui correspondent = ambiguïté refusée.
 *  - Une nouvelle version remplace la précédente à partir de sa date d'effet ; la version remplacée reste
 *    applicable aux créances générées avant cette date. L'application aux créances existantes est
 *    facultative, explicite, et tracée par un ajustement « révision de barème ».
 */
class FeeScheduleService
{
    public function __construct(private NumberingService $numbering) {}

    /** @param array<int, array{label:string, mode:string, value:mixed, due_date:string}> $installments */
    public function createDraft(array $data, array $installments, User $user, ?FeeSchedule $replaces = null): FeeSchedule
    {
        Authz::need($user, 'fees.manage');
        if ($replaces) {
            $data['academic_year_id'] = $replaces->academic_year_id;
            $data['fee_category_id'] = $replaces->fee_category_id;
        }
        YearGuard::assertWritable((int) $data['academic_year_id']);
        $category = FeeCategory::query()->findOrFail($data['fee_category_id']);
        if (! Money::isPositive($data['amount'] ?? 0)) {
            throw new BusinessException('Le montant du barème doit être positif.');
        }
        $computed = $this->computeInstallments($data['amount'], $installments);
        if ($category->bankAccounts()->where('currency', $data['currency'])->where('is_active', true)->doesntExist()) {
            throw new BusinessException("Aucun compte bancaire actif en {$data['currency']} n'est autorisé pour « {$category->label} ». Configurez les comptes autorisés du frais.");
        }
        if ($replaces) {
            if ($replaces->status !== 'active') {
                throw new BusinessException('Seul un barème actif peut être révisé.');
            }
            foreach (['fee_category_id', 'academic_year_id', 'faculty_id', 'program_id', 'promotion_id', 'cycle_id', 'student_status'] as $k) {
                $data[$k] = $replaces->{$k};
            }
            if (trim((string) ($data['change_reason'] ?? '')) === '') {
                throw new BusinessException('Le motif de la modification du barème est obligatoire.');
            }
        }

        return DB::transaction(function () use ($data, $computed, $user, $replaces) {
            $schedule = FeeSchedule::create([
                'fee_category_id' => $data['fee_category_id'],
                'academic_year_id' => $data['academic_year_id'],
                'faculty_id' => $data['faculty_id'] ?? null,
                'program_id' => $data['program_id'] ?? null,
                'promotion_id' => $data['promotion_id'] ?? null,
                'cycle_id' => $data['cycle_id'] ?? null,
                'student_status' => $data['student_status'] ?? null,
                'currency' => $data['currency'],
                'amount' => Money::money($data['amount']),
                'effective_from' => $data['effective_from'],
                'apply_to_existing' => (bool) ($data['apply_to_existing'] ?? false),
                'change_reason' => $data['change_reason'] ?? null,
                'replaces_id' => $replaces?->id,
                'version' => $replaces ? $replaces->version + 1 : 1,
                'status' => 'draft',
                'created_by' => $user->id,
            ]);
            foreach ($computed as $i => $inst) {
                $schedule->installments()->create($inst + ['sequence' => $i + 1]);
            }
            AuditService::log('fee_schedule.draft', $schedule, "Barème v{$schedule->version} préparé : ".Money::format($schedule->amount, $schedule->currency).' — '.$schedule->criteriaLabel());

            return $schedule;
        });
    }

    /**
     * Calcule et vérifie les tranches. En mode pourcentage, la somme doit faire 100 % ; l'écart d'arrondi
     * éventuel (quelques centimes) est porté sur la dernière tranche. En mode montant, la somme doit être exacte.
     */
    public function computeInstallments(mixed $total, array $installments): array
    {
        if (count($installments) === 0) {
            throw new BusinessException('Définissez au moins une tranche (une tranche unique = paiement intégral).');
        }
        $out = [];
        $sum = Money::of(0);
        $percentSum = Money::of(0);
        $modes = array_unique(array_column($installments, 'mode'));
        if (count($modes) > 1) {
            throw new BusinessException('Toutes les tranches doivent utiliser le même mode (montant ou pourcentage).');
        }
        $previousDate = null;
        foreach (array_values($installments) as $i => $inst) {
            $n = $i + 1;
            if (! Money::isPositive($inst['value'] ?? 0)) {
                throw new BusinessException("Tranche {$n} : valeur invalide.");
            }
            $date = Carbon::parse($inst['due_date']);
            if ($previousDate && $date->lt($previousDate)) {
                throw new BusinessException("Tranche {$n} : la date limite doit être postérieure à celle de la tranche précédente.");
            }
            $previousDate = $date;
            $amount = $inst['mode'] === 'percent' ? Money::percentOf($total, $inst['value']) : Money::of(Money::money($inst['value']));
            if ($inst['mode'] === 'percent') {
                $percentSum = $percentSum->plus(Money::of($inst['value']));
            }
            $out[] = ['label' => $inst['label'] ?: "Tranche {$n}", 'mode' => $inst['mode'], 'value' => Money::money($inst['value']), 'amount' => Money::money($amount), 'due_date' => $date->toDateString()];
            $sum = $sum->plus($amount);
        }
        if ($modes === ['percent']) {
            if (Money::cmp($percentSum, 100) !== 0) {
                throw new BusinessException('La somme des pourcentages des tranches doit être égale à 100 % (actuellement '.$percentSum.' %).');
            }
            $diff = Money::sub($total, $sum);
            if (! $diff->isZero()) {
                $last = count($out) - 1;
                $out[$last]['amount'] = Money::money(Money::add($out[$last]['amount'], $diff));
            }
        } elseif (Money::cmp($sum, $total) !== 0) {
            throw new BusinessException('Le total des tranches ('.Money::format($sum).') ne correspond pas au montant du barème ('.Money::format($total).').');
        }

        return $out;
    }

    public function approve(FeeSchedule $schedule, User $approver, ?string $note = null): FeeSchedule
    {
        return DB::transaction(function () use ($schedule, $approver, $note) {
            Authz::need($approver, 'fees.approve');
            $schedule = FeeSchedule::query()->lockForUpdate()->findOrFail($schedule->id);
            if ($schedule->status !== 'draft') {
                throw new BusinessException('Ce barème a déjà été traité.');
            }
            YearGuard::assertWritable($schedule->academic_year_id);
            if (Settings::bool('segregation_of_duties') && $schedule->created_by === $approver->id) {
                throw new BusinessException('Séparation des tâches : le barème doit être approuvé par une autre personne que son auteur.');
            }
            $sameCriteria = FeeSchedule::query()
                ->where('fee_category_id', $schedule->fee_category_id)->where('academic_year_id', $schedule->academic_year_id)
                ->where('status', 'active')->where('id', '!=', $schedule->id);
            foreach (['faculty_id', 'program_id', 'promotion_id', 'cycle_id', 'student_status'] as $k) {
                $schedule->{$k} === null ? $sameCriteria->whereNull($k) : $sameCriteria->where($k, $schedule->{$k});
            }
            $existing = $sameCriteria->lockForUpdate()->get();
            foreach ($existing as $ex) {
                if ($ex->id !== $schedule->replaces_id) {
                    throw new BusinessException('Un barème actif existe déjà pour ces critères (v'.$ex->version.'). Créez une nouvelle version de ce barème.');
                }
            }
            if ($schedule->replaces_id) {
                FeeSchedule::query()->whereKey($schedule->replaces_id)->update(['status' => 'superseded']);
            }
            $schedule->update(['status' => 'active', 'approved_by' => $approver->id, 'approved_at' => now()]);
            AuditService::log('fee_schedule.approve', $schedule, "Barème v{$schedule->version} approuvé ({$schedule->criteriaLabel()})", null, null, $note);

            if ($schedule->replaces_id && $schedule->apply_to_existing) {
                $this->applyToExistingCharges($schedule, $approver);
            }

            return $schedule;
        });
    }

    public function reject(FeeSchedule $schedule, User $approver, string $reason): FeeSchedule
    {
        Authz::need($approver, 'fees.approve');
        if ($schedule->status !== 'draft') {
            throw new BusinessException('Ce barème a déjà été traité.');
        }
        $schedule->update(['status' => 'rejected', 'approved_by' => $approver->id, 'approved_at' => now()]);
        AuditService::log('fee_schedule.reject', $schedule, 'Barème rejeté', null, null, $reason);

        return $schedule;
    }

    /** Révision explicite des créances existantes : ajustement tracé (jamais de modification du montant initial). */
    private function applyToExistingCharges(FeeSchedule $schedule, User $approver): void
    {
        $charges = Charge::query()->where('fee_schedule_id', $schedule->replaces_id)->where('status', 'active')->lockForUpdate()->get();
        foreach ($charges as $charge) {
            $currentNet = Money::of($charge->netAmount());
            $target = Money::of($schedule->amount);
            $diff = $currentNet->minus($target);             // positif = baisse de la créance
            if ($diff->isZero()) {
                continue;
            }
            if ($diff->isPositive() && Money::cmp($diff, $charge->balance()) > 0) {
                // La baisse dépasserait le solde restant : on la limite pour ne pas créer de trop-perçu implicite.
                $diff = Money::of($charge->balance());
                if ($diff->isZero()) {
                    continue;
                }
            }
            ChargeAdjustment::create([
                'number' => $this->numbering->next('adjustment'),
                'charge_id' => $charge->id,
                'type' => 'schedule_revision',
                'amount' => Money::money($diff),
                'reason' => "Révision du barème v{$schedule->version} : ".$schedule->change_reason,
                'status' => 'approved',
                'requested_by' => $schedule->created_by,
                'approved_by' => $approver->id,
                'approved_at' => now(),
            ]);
            $charge->installments()->delete();
            foreach ($schedule->installments as $inst) {
                $charge->installments()->create(['sequence' => $inst->sequence, 'label' => $inst->label, 'amount' => $inst->amount, 'due_date' => $inst->due_date]);
            }
            $charge->update(['fee_schedule_id' => $schedule->id]);
        }
        AuditService::log('fee_schedule.apply_existing', $schedule, "Révision appliquée à {$charges->count()} créance(s) existante(s)");
    }

    /** Barème applicable à une inscription (le plus spécifique, version en vigueur à la date de référence). */
    public function resolve(FeeCategory $category, Enrollment $enrollment, ?Carbon $referenceDate = null): ?FeeSchedule
    {
        $referenceDate ??= now();
        $promotion = $enrollment->promotion()->with('program.department')->first();
        $candidates = FeeSchedule::query()
            ->where('fee_category_id', $category->id)
            ->where('academic_year_id', $enrollment->academic_year_id)
            ->whereIn('status', ['active', 'superseded'])
            ->whereDate('effective_from', '<=', $referenceDate->toDateString())
            ->with('installments')->get()
            ->filter(fn (FeeSchedule $s) => ($s->faculty_id === null || $s->faculty_id === $promotion->program->department->faculty_id)
                && ($s->program_id === null || $s->program_id === $promotion->program_id)
                && ($s->promotion_id === null || $s->promotion_id === $promotion->id)
                && ($s->cycle_id === null || $s->cycle_id === $promotion->program->cycle_id)
                && ($s->student_status === null || $s->student_status === $enrollment->status));
        if ($candidates->isEmpty()) {
            return null;
        }
        // Par jeu de critères, la version la plus récente en vigueur.
        $latest = $candidates->groupBy(fn ($s) => implode('|', [$s->faculty_id, $s->program_id, $s->promotion_id, $s->cycle_id, $s->student_status]))
            ->map(fn ($g) => $g->sortByDesc('version')->first());
        $score = fn ($s) => (int) ($s->faculty_id !== null) + (int) ($s->program_id !== null) + (int) ($s->promotion_id !== null) * 2 + (int) ($s->cycle_id !== null) + (int) ($s->student_status !== null);
        $best = $latest->sortByDesc($score)->values();
        if ($best->count() > 1 && $score($best[0]) === $score($best[1])) {
            throw new BusinessException("Barèmes ambigus pour « {$category->label} » (même niveau de précision). Précisez les critères des barèmes.");
        }

        return $best[0];
    }
}
