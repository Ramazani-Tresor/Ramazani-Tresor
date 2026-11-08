<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\Money;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Seul point d'écriture du stock. Chaque variation de quantité produit un mouvement
 * dans `stock_movements`, dans la même transaction que la mise à jour du produit.
 *
 * Valorisation : coût moyen pondéré (CMP) mobile, recalculé à chaque entrée valorisée :
 *   CMP = (stock_avant × CMP_avant + qté_entrée × coût_entrée) / (stock_avant + qté_entrée)
 * Les sorties sont valorisées au CMP courant, conservé dans le mouvement et dans la ligne de vente.
 */
class StockService
{
    /** Types d'entrées qui recalculent le CMP. */
    private const VALUED_ENTRIES = ['initial', 'purchase', 'customer_return', 'sale_cancel'];

    /**
     * Verrouille les produits (SELECT ... FOR UPDATE), toujours dans l'ordre des identifiants
     * pour éviter les interblocages entre transactions concurrentes.
     *
     * @return Collection<int, Product> indexée par id
     */
    public function lockProducts(array $ids): Collection
    {
        $this->assertInTransaction();
        $ids = array_values(array_unique(array_map('intval', $ids)));
        sort($ids);

        return Product::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
    }

    /**
     * Enregistre un mouvement sur un produit déjà verrouillé.
     *
     * @param  string  $quantity  quantité signée (+ entrée, - sortie)
     */
    public function move(
        Product $product,
        string $type,
        string $quantity,
        User $user,
        ?Model $source = null,
        ?string $sourceNumber = null,
        ?string $reason = null,
        string $bucket = 'available',
        ?string $unitCost = null,
        bool $allowNegative = false,
    ): StockMovement {
        $this->assertInTransaction();

        if ($product->isService()) {
            throw new BusinessException("« {$product->name} » est un service : il ne génère aucun mouvement de stock.");
        }
        $qty = Money::of($quantity);
        if ($qty->isZero()) {
            throw new BusinessException('La quantité d\'un mouvement de stock ne peut pas être nulle.');
        }

        $column = $bucket === 'damaged' ? 'damaged_quantity' : 'stock_quantity';
        $before = Money::of($product->{$column});
        $after = $before->plus($qty);

        $negativeAllowed = $allowNegative || ($bucket === 'available' && Settings::bool('allow_negative_stock'));
        if ($after->isNegative() && ! $negativeAllowed) {
            throw new BusinessException(sprintf(
                'Stock insuffisant pour « %s » : disponible %s, demandé %s.',
                $product->name,
                Money::formatQty($before),
                Money::formatQty($qty->abs())
            ), ['product_id' => $product->id, 'available' => (string) $before]);
        }

        $cost = $unitCost !== null ? Money::of($unitCost) : Money::of($product->average_cost);

        if ($bucket === 'available' && $qty->isPositive() && in_array($type, self::VALUED_ENTRIES, true)) {
            if (Money::cmp($before, 0) <= 0) {
                $product->average_cost = Money::cost($cost);
            } else {
                $value = $before->multipliedBy(Money::of($product->average_cost))->plus($qty->multipliedBy($cost));
                $product->average_cost = Money::cost(Money::div($value, $after, Money::COST_SCALE));
            }
        }

        $product->{$column} = Money::qty($after);
        $product->save();

        return StockMovement::create([
            'product_id' => $product->id,
            'type' => $type,
            'bucket' => $bucket,
            'quantity' => Money::qty($qty),
            'unit_cost' => Money::cost($cost),
            'balance_after' => Money::qty($after),
            'reason' => $reason,
            'source_type' => $source?->getMorphClass(),
            'source_id' => $source?->getKey(),
            'source_number' => $sourceNumber,
            'user_id' => $user->id,
            'occurred_at' => now(),
        ]);
    }

    /** Sortie manuelle : perte/casse, retour fournisseur, mise au rebut du stock endommagé. */
    public function manualExit(Product $product, string $type, string $quantity, string $reason, User $user): StockMovement
    {
        if (! in_array($type, StockMovement::MANUAL_EXIT_TYPES, true)) {
            throw new BusinessException('Type de sortie de stock non autorisé.');
        }
        if (! Money::isPositive($quantity)) {
            throw new BusinessException('La quantité doit être strictement positive.');
        }

        return DB::transaction(function () use ($product, $type, $quantity, $reason, $user) {
            $locked = $this->lockProducts([$product->id])->get($product->id);
            $bucket = $type === 'damaged_writeoff' ? 'damaged' : 'available';
            $movement = $this->move($locked, $type, '-'.Money::qty($quantity), $user, null, null, $reason, $bucket);
            AuditService::log('stock.exit', $locked, StockMovement::TYPES[$type].' : '.Money::formatQty($quantity).' — '.$reason);

            return $movement;
        });
    }

    /** Vérifie la cohérence : quantité du produit = somme des mouvements. */
    public function recomputedQuantity(Product $product, string $bucket = 'available'): string
    {
        return Money::qty(StockMovement::query()->where('product_id', $product->id)->where('bucket', $bucket)->sum('quantity'));
    }

    private function assertInTransaction(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Les opérations de stock doivent être exécutées dans une transaction.');
        }
    }
}
