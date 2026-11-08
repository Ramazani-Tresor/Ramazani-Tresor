<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\User;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

/**
 * Bons d'entrée (approvisionnements). Un bon est saisi en brouillon, puis validé :
 * la validation crée les mouvements « purchase » et met à jour le CMP.
 * Les approvisionnements ne sont PAS des dépenses d'exploitation (pas de double comptabilisation) :
 * leur coût entre dans le coût des marchandises vendues au fil des ventes.
 */
class PurchaseService
{
    public function __construct(
        private StockService $stock,
        private CashService $cash,
        private NumberingService $numbering,
    ) {}

    /** @param array<int, array{product_id:int, quantity:mixed, unit_cost:mixed}> $items */
    public function saveDraft(?Purchase $purchase, array $header, array $items, User $user): Purchase
    {
        if (count($items) === 0) {
            throw new BusinessException('Ajoutez au moins un article au bon d\'entrée.');
        }

        return DB::transaction(function () use ($purchase, $header, $items, $user) {
            if ($purchase) {
                $purchase = Purchase::query()->lockForUpdate()->findOrFail($purchase->id);
                if (! $purchase->isDraft()) {
                    throw new BusinessException('Un bon validé ou annulé ne peut plus être modifié.');
                }
                $purchase->fill($header)->save();
                $purchase->items()->delete();
            } else {
                $purchase = Purchase::create($header + [
                    'number' => $this->numbering->next('purchase'),
                    'status' => 'draft',
                    'user_id' => $user->id,
                ]);
            }

            $products = Product::query()->whereIn('id', array_column($items, 'product_id'))->get()->keyBy('id');
            $total = BigDecimal::zero();
            foreach ($items as $i => $item) {
                $product = $products->get((int) $item['product_id']);
                if (! $product) {
                    throw new BusinessException('Ligne '.($i + 1).' : article introuvable.');
                }
                if ($product->isService()) {
                    throw new BusinessException("« {$product->name} » est un service : il ne peut pas être approvisionné.");
                }
                $qty = Money::of(Money::qty($item['quantity']));
                $cost = Money::of(Money::money($item['unit_cost']));
                if (! $qty->isPositive() || $cost->isNegative()) {
                    throw new BusinessException("Quantité ou coût invalide pour « {$product->name} ».");
                }
                $line = Money::mul($qty, $cost);
                $purchase->items()->create([
                    'product_id' => $product->id,
                    'quantity' => Money::qty($qty),
                    'unit_cost' => Money::money($cost),
                    'line_total' => Money::money($line),
                ]);
                $total = $total->plus($line);
            }
            $purchase->update(['total' => Money::money($total)]);
            AuditService::log('purchase.save', $purchase, "Bon d'entrée {$purchase->number} enregistré (brouillon)");

            return $purchase;
        });
    }

    public function validate(Purchase $purchase, User $user): Purchase
    {
        return DB::transaction(function () use ($purchase, $user) {
            $purchase = Purchase::query()->lockForUpdate()->findOrFail($purchase->id);
            if (! $purchase->isDraft()) {
                throw new BusinessException('Ce bon d\'entrée a déjà été traité.');
            }
            $items = $purchase->items()->get();
            if ($items->isEmpty()) {
                throw new BusinessException('Le bon d\'entrée est vide.');
            }
            $products = $this->stock->lockProducts($items->pluck('product_id')->all());
            foreach ($items as $item) {
                $product = $products->get($item->product_id);
                $this->stock->move($product, 'purchase', (string) $item->quantity, $user, $purchase, $purchase->number,
                    'Approvisionnement '.($purchase->supplier_reference ? 'réf. '.$purchase->supplier_reference : ''), 'available', (string) $item->unit_cost);
            }

            if ($purchase->paid_from_cash) {
                $session = $this->cash->requireOpenSession($user);
                if (Money::cmp($this->cash->expectedCash($session), $purchase->total) < 0) {
                    throw new BusinessException('Espèces insuffisantes en caisse pour payer ce fournisseur.');
                }
                $this->cash->record($session, PaymentMethod::cash(), 'supplier_payment', '-'.Money::money($purchase->total), $user, "Paiement fournisseur {$purchase->number}", $purchase);
            }

            $purchase->update(['status' => 'validated', 'validated_by' => $user->id, 'validated_at' => now()]);
            AuditService::log('purchase.validate', $purchase, "Validation du bon {$purchase->number} : ".Money::format($purchase->total));

            return $purchase;
        });
    }

    public function cancel(Purchase $purchase, User $user): Purchase
    {
        return DB::transaction(function () use ($purchase, $user) {
            $purchase = Purchase::query()->lockForUpdate()->findOrFail($purchase->id);
            if (! $purchase->isDraft()) {
                throw new BusinessException('Seul un brouillon peut être annulé. Pour un bon validé, enregistrez un retour fournisseur.');
            }
            $purchase->update(['status' => 'cancelled']);
            AuditService::log('purchase.cancel', $purchase, "Annulation du brouillon {$purchase->number}", null, null, $user->id);

            return $purchase;
        });
    }
}
