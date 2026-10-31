<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Expense;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Dépenses d'exploitation. Une dépense n'affecte la caisse que lorsqu'elle est validée ET
 * marquée « payée depuis la caisse » : le décaissement est alors imputé à la session ouverte du valideur.
 */
class ExpenseService
{
    public function __construct(private CashService $cash, private NumberingService $numbering) {}

    public function create(array $data, User $user): Expense
    {
        if (! Money::isPositive($data['amount'] ?? 0)) {
            throw new BusinessException('Le montant de la dépense doit être positif.');
        }

        return DB::transaction(function () use ($data, $user) {
            $expense = Expense::create($data + [
                'number' => $this->numbering->next('expense'),
                'status' => 'pending',
                'user_id' => $user->id,
            ]);
            AuditService::log('expense.create', $expense, "Dépense {$expense->number} : ".Money::format($expense->amount).' — '.$expense->reason);

            if ($user->hasPermission('expenses.approve')) {
                $this->approve($expense, $user);
            }

            return $expense->refresh();
        });
    }

    public function approve(Expense $expense, User $approver): Expense
    {
        return DB::transaction(function () use ($expense, $approver) {
            $expense = Expense::query()->lockForUpdate()->findOrFail($expense->id);
            if ($expense->status !== 'pending') {
                throw new BusinessException('Cette dépense a déjà été traitée.');
            }
            $sessionId = null;
            if ($expense->paid_from_cash) {
                $method = PaymentMethod::query()->findOrFail($expense->payment_method_id);
                if (! $method->isCash()) {
                    throw new BusinessException('Une dépense payée depuis la caisse doit utiliser un mode de paiement en espèces.');
                }
                $session = $this->cash->requireOpenSession($approver);
                if (Money::cmp($this->cash->expectedCash($session), $expense->amount) < 0) {
                    throw new BusinessException('Espèces insuffisantes en caisse pour payer cette dépense.');
                }
                $this->cash->record($session, $method, 'expense', '-'.Money::money($expense->amount), $approver, "Dépense {$expense->number} — {$expense->beneficiary}", $expense);
                $sessionId = $session->id;
            }
            $expense->update([
                'status' => 'approved',
                'approved_by' => $approver->id,
                'approved_at' => now(),
                'cash_session_id' => $sessionId,
            ]);
            AuditService::log('expense.approve', $expense, "Validation de la dépense {$expense->number}");

            return $expense;
        });
    }

    public function reject(Expense $expense, User $approver, string $reason): Expense
    {
        return DB::transaction(function () use ($expense, $approver, $reason) {
            $expense = Expense::query()->lockForUpdate()->findOrFail($expense->id);
            if ($expense->status !== 'pending') {
                throw new BusinessException('Cette dépense a déjà été traitée.');
            }
            $expense->update(['status' => 'rejected', 'approved_by' => $approver->id, 'approved_at' => now(), 'rejection_reason' => $reason]);
            AuditService::log('expense.reject', $expense, "Rejet de la dépense {$expense->number} : {$reason}");

            return $expense;
        });
    }
}
