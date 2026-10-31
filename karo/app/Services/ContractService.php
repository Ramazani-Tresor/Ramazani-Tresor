<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Charge;
use App\Models\PaymentContract;
use App\Models\Student;
use App\Models\User;
use App\Support\Authz;
use App\Support\Money;
use App\Support\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Contrats (engagements) de paiement. Toute modification crée une nouvelle version (même numéro,
 * version + 1) ; la précédente passe à « remplacée ». La version signée est archivée (fichier privé).
 * Contrat non respecté : cumul des échéances échues > paiements reçus depuis la signature.
 */
class ContractService
{
    public function __construct(private NumberingService $numbering) {}

    /** @param array<int, array{amount:mixed, due_date:string}> $installments */
    public function create(Student $student, int $yearId, array $chargeIds, array $installments, ?string $conditions, User $user, ?PaymentContract $previous = null): PaymentContract
    {
        YearGuard::assertWritable($yearId);
        $charges = Charge::query()->whereIn('id', $chargeIds ?: [0])->where('student_id', $student->id)->where('academic_year_id', $yearId)->where('status', 'active')->get();
        if ($charges->isEmpty()) {
            throw new BusinessException('Sélectionnez au moins une créance de l’année.');
        }
        $currencies = $charges->pluck('currency')->unique();
        if ($currencies->count() > 1) {
            throw new BusinessException('Un contrat ne peut couvrir qu’une seule devise : créez un contrat par devise.');
        }
        $total = Money::add(...$charges->map(fn ($c) => $c->netAmount())->all());
        $paid = Money::add(...$charges->map(fn ($c) => $c->settledAmount())->all());
        $balance = $total->minus($paid);
        if (! $balance->isPositive()) {
            throw new BusinessException('Ces créances sont soldées : aucun contrat n’est nécessaire.');
        }
        $sum = Money::of(0);
        $prev = null;
        foreach (array_values($installments) as $i => $inst) {
            if (! Money::isPositive($inst['amount'] ?? 0)) {
                throw new BusinessException('Échéance '.($i + 1).' : montant invalide.');
            }
            $d = Carbon::parse($inst['due_date']);
            if ($prev && $d->lt($prev)) {
                throw new BusinessException('Les échéances doivent être en ordre chronologique.');
            }
            $prev = $d;
            $sum = $sum->plus(Money::of(Money::money($inst['amount'])));
        }
        if (Money::cmp($sum, $balance) !== 0) {
            throw new BusinessException('Le total des échéances ('.Money::format($sum).') doit être égal au solde à couvrir ('.Money::format($balance).').');
        }

        return DB::transaction(function () use ($student, $yearId, $charges, $installments, $conditions, $user, $previous, $total, $paid, $balance, $currencies) {
            $contract = PaymentContract::create([
                'number' => $previous?->number ?? $this->numbering->next('contract'),
                'version' => $previous ? $previous->version + 1 : 1,
                'parent_id' => $previous?->id,
                'student_id' => $student->id,
                'academic_year_id' => $yearId,
                'currency' => $currencies->first(),
                'total_due' => Money::money($total),
                'paid_at_signature' => Money::money($paid),
                'balance' => Money::money($balance),
                'charge_ids' => $charges->pluck('id')->all(),
                'conditions' => $conditions ?: Settings::get('contract_default_conditions'),
                'signatories' => [
                    ['name' => Settings::get('signatory_1_name'), 'title' => Settings::get('signatory_1_title')],
                    ['name' => $student->fullName(), 'title' => 'Étudiant(e)'],
                ],
                'status' => 'draft',
                'created_by' => $user->id,
            ]);
            foreach (array_values($installments) as $i => $inst) {
                $contract->installments()->create(['sequence' => $i + 1, 'amount' => Money::money($inst['amount']), 'due_date' => $inst['due_date']]);
            }
            AuditService::log('contract.create', $contract, "Contrat {$contract->number} v{$contract->version} préparé : solde ".Money::format($balance, $contract->currency));

            return $contract;
        });
    }

    public function newVersion(PaymentContract $contract, array $installments, ?string $conditions, string $reason, User $user): PaymentContract
    {
        if (! in_array($contract->status, ['draft', 'active', 'signed'], true)) {
            throw new BusinessException('Seule la version en vigueur peut être modifiée.');
        }
        if (trim($reason) === '') {
            throw new BusinessException('Le motif de la nouvelle version est obligatoire.');
        }

        return DB::transaction(function () use ($contract, $installments, $conditions, $reason, $user) {
            $new = $this->create($contract->student, $contract->academic_year_id, $contract->charge_ids, $installments, $conditions ?? $contract->conditions, $user, $contract);
            $contract->update(['status' => 'superseded']);
            AuditService::log('contract.version', $new, "Nouvelle version v{$new->version} du contrat {$new->number}", null, null, $reason);

            return $new;
        });
    }

    public function approve(PaymentContract $contract, User $approver): PaymentContract
    {
        Authz::need($approver, 'contracts.approve');
        if ($contract->status !== 'draft') {
            throw new BusinessException('Seul un brouillon peut être approuvé.');
        }
        YearGuard::assertWritable($contract->academic_year_id);
        if (Settings::bool('segregation_of_duties') && $contract->created_by === $approver->id) {
            throw new BusinessException('Séparation des tâches : le contrat doit être approuvé par une autre personne que son auteur.');
        }
        $contract->update(['status' => 'active', 'approved_by' => $approver->id, 'approved_at' => now()]);
        AuditService::log('contract.approve', $contract, "Approbation du contrat {$contract->number} v{$contract->version}");

        return $contract;
    }

    public function archiveSigned(PaymentContract $contract, string $path, User $user): PaymentContract
    {
        if ($contract->status !== 'active') {
            throw new BusinessException('Seul un contrat approuvé peut recevoir sa version signée.');
        }
        $contract->update(['status' => 'signed', 'signed_file_path' => $path]);
        AuditService::log('contract.signed', $contract, "Version signée archivée ({$contract->number} v{$contract->version})", null, null, null, $user->id);

        return $contract;
    }

    /** @return array{due_so_far:string, paid_since:string, shortfall:string, breached:bool} */
    public function compliance(PaymentContract $contract, ?Carbon $asOf = null): array
    {
        $asOf ??= now();
        $due = Money::add(...$contract->installments->filter(fn ($i) => $i->due_date->lte($asOf))->pluck('amount')->all());
        $settledNow = Money::add(...Charge::query()->whereIn('id', $contract->charge_ids)->get()->map(fn ($c) => $c->settledAmount())->all());
        $paidSince = Money::max(0, $settledNow->minus(Money::of($contract->paid_at_signature)));
        $shortfall = Money::max(0, $due->minus($paidSince));

        return ['due_so_far' => Money::money($due), 'paid_since' => Money::money($paidSince), 'shortfall' => Money::money($shortfall),
            'breached' => in_array($contract->status, ['active', 'signed'], true) && $shortfall->isPositive()];
    }
}
