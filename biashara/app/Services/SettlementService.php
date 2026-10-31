<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Customer;
use App\Models\CustomerSettlement;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Règlement ultérieur d'une créance client. Ce n'est PAS une vente : aucune ligne
 * ni mouvement de stock, seulement une imputation sur les ventes à crédit (plus anciennes d'abord).
 * L'identifiant client_uuid empêche qu'un même règlement soit comptabilisé deux fois.
 */
class SettlementService
{
    public function __construct(private CashService $cash, private NumberingService $numbering) {}

    public function settle(Customer $customer, string $amount, PaymentMethod $method, User $user, string $clientUuid, ?string $reference = null, ?string $notes = null, ?int $saleId = null): CustomerSettlement
    {
        if ($existing = CustomerSettlement::query()->where('client_uuid', $clientUuid)->first()) {
            return $existing;
        }
        if (! Money::isPositive($amount)) {
            throw new BusinessException('Le montant du règlement doit être positif.');
        }

        return DB::transaction(function () use ($customer, $amount, $method, $user, $clientUuid, $reference, $notes, $saleId) {
            $customer = Customer::query()->lockForUpdate()->findOrFail($customer->id);
            $sales = Sale::query()
                ->where('customer_id', $customer->id)
                ->where('status', 'validated')
                ->where('amount_due', '>', 0)
                ->when($saleId, fn ($q) => $q->where('id', $saleId))
                ->orderBy('sold_at')->orderBy('id')
                ->lockForUpdate()->get();

            $totalDue = Money::add(...$sales->pluck('amount_due')->all());
            if ($sales->isEmpty() || Money::cmp($amount, $totalDue) > 0) {
                throw new BusinessException('Le règlement ('.Money::format($amount).') dépasse le solde dû ('.Money::format($totalDue).').');
            }
            $session = $this->cash->requireOpenSession($user);

            $settlement = CustomerSettlement::create([
                'number' => $this->numbering->next('settlement'),
                'client_uuid' => $clientUuid,
                'customer_id' => $customer->id,
                'payment_method_id' => $method->id,
                'cash_session_id' => $session->id,
                'user_id' => $user->id,
                'amount' => Money::money($amount),
                'reference' => $reference,
                'notes' => $notes,
                'paid_at' => now(),
            ]);

            $remaining = Money::of($amount);
            foreach ($sales as $sale) {
                if (! $remaining->isPositive()) {
                    break;
                }
                $part = Money::min($remaining, $sale->amount_due);
                Payment::create([
                    'kind' => 'debt_settlement',
                    'sale_id' => $sale->id,
                    'customer_settlement_id' => $settlement->id,
                    'customer_id' => $customer->id,
                    'payment_method_id' => $method->id,
                    'cash_session_id' => $session->id,
                    'user_id' => $user->id,
                    'amount' => Money::money($part),
                    'currency' => $sale->currency,
                    'currency_amount' => Money::money($part),
                    'exchange_rate' => '1',
                    'reference' => $reference,
                    'paid_at' => now(),
                ]);
                $sale->update([
                    'amount_paid' => Money::money(Money::of($sale->amount_paid)->plus($part)),
                    'amount_due' => Money::money(Money::of($sale->amount_due)->minus($part)),
                ]);
                $remaining = $remaining->minus($part);
            }

            $this->cash->record($session, $method, 'debt_settlement', Money::money($amount), $user, "Règlement {$settlement->number} — {$customer->name}", $settlement);
            AuditService::log('customer.settlement', $settlement, "Règlement de {$customer->name} : ".Money::format($amount));

            return $settlement;
        });
    }
}
