<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Inventory;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Inventaire physique — procédure retenue : « prise en compte des mouvements postérieurs au relevé ».
 *
 * Les ventes ne sont PAS bloquées. Pour chaque article, le stock théorique est figé au moment
 * où le comptage est saisi (et non à l'ouverture). L'écart = compté − théorique au relevé.
 * À la validation, on applique cet écart au stock courant : les ventes ou entrées intervenues
 * entre le relevé et la validation restent donc correctement prises en compte.
 * Recommandation : compter un rayon, saisir immédiatement, puis passer au suivant.
 */
class InventoryService
{
    public function __construct(private StockService $stock, private NumberingService $numbering) {}

    public function open(User $user, ?int $categoryId, ?string $notes): Inventory
    {
        return DB::transaction(function () use ($user, $categoryId, $notes) {
            $overlap = Inventory::query()->where('status', 'open')
                ->where(fn ($q) => $q->whereNull('category_id')->orWhere('category_id', $categoryId)->when($categoryId === null, fn ($q2) => $q2->orWhereNotNull('category_id')))
                ->lockForUpdate()->exists();
            if ($overlap) {
                throw new BusinessException('Un inventaire ouvert couvre déjà ces articles. Validez-le ou annulez-le d\'abord.');
            }
            $inventory = Inventory::create([
                'number' => $this->numbering->next('inventory'),
                'category_id' => $categoryId,
                'status' => 'open',
                'notes' => $notes,
                'opened_by' => $user->id,
                'opened_at' => now(),
            ]);
            $products = Product::query()->where('type', 'product')->where('is_active', true)
                ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))->orderBy('name')->get();
            foreach ($products as $product) {
                $inventory->items()->create(['product_id' => $product->id, 'unit_cost' => $product->average_cost]);
            }
            AuditService::log('inventory.open', $inventory, "Ouverture de l'inventaire {$inventory->number} ({$products->count()} articles)");

            return $inventory;
        });
    }

    /** @param array<int, array{counted_quantity:mixed, justification?:string|null}> $counts indexé par inventory_item_id */
    public function recordCounts(Inventory $inventory, array $counts, User $user): void
    {
        DB::transaction(function () use ($inventory, $counts, $user) {
            $inventory = Inventory::query()->lockForUpdate()->findOrFail($inventory->id);
            if (! $inventory->isOpen()) {
                throw new BusinessException('Cet inventaire est clôturé.');
            }
            $items = $inventory->items()->with('product')->whereIn('id', array_keys($counts))->get()->keyBy('id');
            foreach ($counts as $itemId => $row) {
                $item = $items->get((int) $itemId);
                if (! $item || ! isset($row['counted_quantity']) || $row['counted_quantity'] === '' || $row['counted_quantity'] === null) {
                    continue;
                }
                $counted = Money::of(Money::qty($row['counted_quantity']));
                if ($counted->isNegative()) {
                    throw new BusinessException("Quantité comptée négative pour « {$item->product->name} ».");
                }
                $sameCount = $item->counted_quantity !== null && Money::cmp($item->counted_quantity, $counted) === 0;
                if (! $sameCount) {
                    // Nouveau relevé : le théorique est figé maintenant.
                    $product = Product::query()->findOrFail($item->product_id);
                    $item->theoretical_quantity = $product->stock_quantity;
                    $item->unit_cost = $product->average_cost;
                    $item->last_movement_id = (int) StockMovement::query()->where('product_id', $product->id)->max('id');
                    $item->counted_quantity = Money::qty($counted);
                    $item->difference = Money::qty($counted->minus(Money::of($item->theoretical_quantity)));
                    $item->counted_by = $user->id;
                    $item->counted_at = now();
                }
                $item->justification = $row['justification'] ?? $item->justification;
                $item->save();
            }
        });
    }

    public function validate(Inventory $inventory, User $user): Inventory
    {
        return DB::transaction(function () use ($inventory, $user) {
            $inventory = Inventory::query()->lockForUpdate()->findOrFail($inventory->id);
            if (! $inventory->isOpen()) {
                throw new BusinessException('Cet inventaire est déjà clôturé.');
            }
            $items = $inventory->items()->with('product')->get();
            $counted = $items->whereNotNull('counted_quantity');
            if ($counted->isEmpty()) {
                throw new BusinessException('Aucune quantité n\'a été comptée.');
            }
            $missing = $counted->filter(fn (InventoryItem $i) => ! Money::isZero($i->difference) && trim((string) $i->justification) === '');
            if ($missing->isNotEmpty()) {
                throw new BusinessException('Justifiez chaque écart avant validation : '.$missing->pluck('product.name')->take(5)->implode(', ').'.');
            }

            $products = $this->stock->lockProducts($counted->pluck('product_id')->all());
            foreach ($counted as $item) {
                if (Money::isZero($item->difference)) {
                    continue;
                }
                $product = $products->get($item->product_id);
                $this->stock->move($product, 'inventory_adjustment', (string) $item->difference, $user, $inventory, $inventory->number,
                    'Écart d\'inventaire : '.$item->justification, 'available', (string) $item->unit_cost, true);
            }
            $inventory->update(['status' => 'validated', 'validated_by' => $user->id, 'validated_at' => now()]);
            AuditService::log('inventory.validate', $inventory, "Validation de l'inventaire {$inventory->number}");

            return $inventory;
        });
    }

    public function cancel(Inventory $inventory, User $user): Inventory
    {
        if (! $inventory->isOpen()) {
            throw new BusinessException('Cet inventaire est déjà clôturé.');
        }
        $inventory->update(['status' => 'cancelled']);
        AuditService::log('inventory.cancel', $inventory, "Annulation de l'inventaire {$inventory->number}", null, null, $user->id);

        return $inventory;
    }
}
