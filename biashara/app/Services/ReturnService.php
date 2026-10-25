<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\User;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Retours clients (totaux ou partiels) et annulations de ventes.
 *
 * Règles :
 *  - quantité retournée ≤ quantité vendue − retours précédents ;
 *  - motif obligatoire ;
 *  - articles « revendables » → stock disponible ; « endommagés » → stock endommagé (non vendable) ;
 *  - la valeur du retour éteint d'abord la créance de la vente, le reste peut être remboursé
 *    dans la limite de ce que le client a payé.
 */
class ReturnService
{
    public function __construct(
        private StockService $stock,
        private CashService $cash,
        private NumberingService $numbering,
    ) {}

    /**
     * @param  array<int, array{sale_item_id:int, quantity:mixed, condition:string}>  $lines
     */
    public function create(
        Sale $sale,
        array $lines,
        string $reason,
        User $user,
        User $authorizer,
        ?string $refundAmount = null,
        ?PaymentMethod $refundMethod = null,
        ?string $clientUuid = null,
        bool $isCancellation = false,
    ): SaleReturn {
        if (trim($reason) === '') {
            throw new BusinessException('Le motif du retour est obligatoire.');
        }
        $clientUuid ??= (string) Str::uuid();
        if ($existing = SaleReturn::query()->where('client_uuid', $clientUuid)->first()) {
            return $existing;
        }

        return DB::transaction(function () use ($sale, $lines, $reason, $user, $authorizer, $refundAmount, $refundMethod, $clientUuid, $isCancellation) {
            $sale = Sale::query()->lockForUpdate()->findOrFail($sale->id);
            if ($sale->isCancelled()) {
                throw new BusinessException('Cette vente est déjà annulée.');
            }
            $saleItems = SaleItem::query()->where('sale_id', $sale->id)->lockForUpdate()->get()->keyBy('id');

            $prepared = [];
            $totalValue = BigDecimal::zero();
            foreach ($lines as $line) {
                $qty = Money::of(Money::qty($line['quantity'] ?? 0));
                if ($qty->isZero()) {
                    continue;
                }
                /** @var SaleItem|null $item */
                $item = $saleItems->get((int) $line['sale_item_id']);
                if (! $item) {
                    throw new BusinessException('Article non présent dans cette vente.');
                }
                if ($qty->isNegative()) {
                    throw new BusinessException("Quantité invalide pour « {$item->product_name} ».");
                }
                $returnable = Money::of($item->returnableQuantity());
                if (Money::cmp($qty, $returnable) > 0) {
                    throw new BusinessException(sprintf(
                        'Quantité retournée trop élevée pour « %s » : vendu %s, déjà retourné %s, maximum %s.',
                        $item->product_name,
                        Money::formatQty($item->quantity),
                        Money::formatQty($item->returned_quantity),
                        Money::formatQty($returnable)
                    ));
                }
                $condition = ($line['condition'] ?? 'resaleable') === 'damaged' ? 'damaged' : 'resaleable';

                // Retour du solde complet d'une ligne : on reprend exactement le reliquat de valeur (pas d'arrondi résiduel).
                if ($qty->isEqualTo($returnable)) {
                    $already = SaleReturnItem::query()->where('sale_item_id', $item->id)->sum('line_value');
                    $value = Money::of($item->line_total)->minus(Money::of($already));
                } else {
                    $value = Money::mul($item->unitValue(), $qty);
                }
                $prepared[] = compact('item', 'qty', 'condition', 'value');
                $totalValue = $totalValue->plus($value);
            }
            if (count($prepared) === 0) {
                throw new BusinessException('Sélectionnez au moins un article à retourner.');
            }

            $dueReduction = Money::min($sale->amount_due, $totalValue);
            $refundable = Money::min(
                $totalValue->minus($dueReduction),
                Money::of($sale->amount_paid)->minus(Money::of($sale->refunded_total))
            );
            $refundable = Money::max(0, $refundable);
            $refund = $refundAmount === null ? $refundable : Money::of(Money::money($refundAmount));
            if ($refund->isNegative() || Money::cmp($refund, $refundable) > 0) {
                throw new BusinessException('Le remboursement doit être compris entre 0 et '.Money::format($refundable).'.');
            }

            $session = null;
            if ($refund->isPositive()) {
                $refundMethod ??= PaymentMethod::cash();
                $session = $this->cash->requireOpenSession($user);
                if ($refundMethod->isCash() && Money::cmp($this->cash->expectedCash($session), $refund) < 0) {
                    throw new BusinessException('Espèces insuffisantes en caisse pour ce remboursement.');
                }
            }

            $return = SaleReturn::create([
                'number' => $this->numbering->next('sale_return'),
                'client_uuid' => $clientUuid,
                'sale_id' => $sale->id,
                'user_id' => $user->id,
                'authorized_by' => $authorizer->id,
                'cash_session_id' => $session?->id,
                'refund_method_id' => $refund->isPositive() ? $refundMethod->id : null,
                'reason' => $reason,
                'total_value' => Money::money($totalValue),
                'due_reduction' => Money::money($dueReduction),
                'refund_amount' => Money::money($refund),
                'is_cancellation' => $isCancellation,
                'returned_at' => now(),
            ]);

            $products = $this->stock->lockProducts(array_map(fn ($p) => $p['item']->product_id, $prepared));
            foreach ($prepared as $p) {
                /** @var SaleItem $item */
                $item = $p['item'];
                SaleReturnItem::create([
                    'sale_return_id' => $return->id,
                    'sale_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'quantity' => Money::qty($p['qty']),
                    'condition' => $p['condition'],
                    'unit_value' => $item->unitValue(),
                    'line_value' => Money::money($p['value']),
                    'unit_cost' => $item->unit_cost,
                ]);
                $item->returned_quantity = Money::qty(Money::of($item->returned_quantity)->plus($p['qty']));
                $item->save();

                if ($item->product_type === 'product') {
                    $product = $products->get($item->product_id);
                    $type = $isCancellation ? 'sale_cancel' : ($p['condition'] === 'damaged' ? 'customer_return_damaged' : 'customer_return');
                    $bucket = $p['condition'] === 'damaged' ? 'damaged' : 'available';
                    $this->stock->move($product, $type, Money::qty($p['qty']), $user, $return, $return->number, $reason, $bucket, (string) $item->unit_cost);
                }
            }

            if ($refund->isPositive()) {
                $payment = Payment::create([
                    'kind' => 'refund',
                    'sale_id' => $sale->id,
                    'sale_return_id' => $return->id,
                    'customer_id' => $sale->customer_id,
                    'payment_method_id' => $refundMethod->id,
                    'cash_session_id' => $session->id,
                    'user_id' => $user->id,
                    'amount' => '-'.Money::money($refund),
                    'currency' => $sale->currency,
                    'currency_amount' => '-'.Money::money($refund),
                    'exchange_rate' => '1',
                    'paid_at' => now(),
                ]);
                $this->cash->record($session, $refundMethod, 'refund', '-'.Money::money($refund), $user, 'Remboursement '.$return->number, $payment);
            }

            $sale->update([
                'amount_due' => Money::money(Money::of($sale->amount_due)->minus($dueReduction)),
                'returned_total' => Money::money(Money::of($sale->returned_total)->plus($totalValue)),
                'refunded_total' => Money::money(Money::of($sale->refunded_total)->plus($refund)),
            ]);

            AuditService::log($isCancellation ? 'sale.cancel' : 'sale.return', $return, sprintf(
                '%s %s sur la vente %s — valeur %s, remboursé %s, autorisé par %s. Motif : %s',
                $isCancellation ? 'Annulation' : 'Retour',
                $return->number,
                $sale->number,
                Money::format($totalValue),
                Money::format($refund),
                $authorizer->name,
                $reason
            ));

            return $return;
        });
    }

    public function cancelSale(Sale $sale, string $reason, User $user, ?PaymentMethod $refundMethod = null): Sale
    {
        if (trim($reason) === '') {
            throw new BusinessException("Le motif d'annulation est obligatoire.");
        }

        return DB::transaction(function () use ($sale, $reason, $user, $refundMethod) {
            $sale = Sale::query()->lockForUpdate()->findOrFail($sale->id);
            if ($sale->isCancelled()) {
                throw new BusinessException('Cette vente est déjà annulée.');
            }
            $lines = $sale->items()->get()
                ->filter(fn (SaleItem $i) => Money::isPositive($i->returnableQuantity()))
                ->map(fn (SaleItem $i) => ['sale_item_id' => $i->id, 'quantity' => $i->returnableQuantity(), 'condition' => 'resaleable'])
                ->values()->all();

            if (count($lines) > 0) {
                $this->create($sale, $lines, 'Annulation : '.$reason, $user, $user, null, $refundMethod, null, true);
            }
            $sale->refresh();
            $sale->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by' => $user->id,
                'cancel_reason' => $reason,
            ]);

            return $sale;
        });
    }
}
