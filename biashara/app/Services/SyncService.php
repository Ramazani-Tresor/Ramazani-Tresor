<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Device;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SyncOperation;
use App\Models\User;
use App\Support\Money;
use App\Support\Settings;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

/**
 * Synchronisation des ventes enregistrées hors ligne.
 *
 * Politique retenue : « vente provisoire soumise à validation lors de la synchronisation ».
 * Un appareil déconnecté ne connaît pas le stock réel des autres appareils : la vente est
 * enregistrée localement avec un identifiant unique (UUID), puis rejouée côté serveur.
 *  - aucune anomalie        → vente créée (statut « synchronisée ») ;
 *  - prix changé, stock insuffisant, article désactivé, caisse fermée… → statut « en conflit »,
 *    la vente N'EST PAS modifiée silencieusement : un responsable décide (accepter / rejeter) ;
 *  - un même UUID n'est jamais traité deux fois (contrainte d'unicité + contrôle applicatif).
 */
class SyncService
{
    public function __construct(private SaleService $sales) {}

    /** @return array{status:string, message:?string, conflicts:array, sale_number:?string, sale_id:?int} */
    public function pushSale(array $payload, User $user, string $deviceId): array
    {
        $uuid = (string) ($payload['client_uuid'] ?? '');
        if ($uuid === '') {
            throw new BusinessException('Identifiant de transaction manquant.');
        }
        $this->touchDevice($deviceId, $user, true);

        // Déjà créée (par exemple en ligne, réponse perdue) : on renvoie le résultat sans rien recréer.
        if ($sale = Sale::query()->where('client_uuid', $uuid)->first()) {
            $op = SyncOperation::query()->where('client_uuid', $uuid)->first();
            if ($op && $op->status !== 'synced') {
                $op->update(['status' => 'synced', 'result_type' => $sale->getMorphClass(), 'result_id' => $sale->id]);
            }

            return $this->result('synced', null, [], $sale);
        }

        $op = SyncOperation::query()->where('client_uuid', $uuid)->first();
        if ($op && $op->status === 'rejected') {
            return $this->result('rejected', $op->message ?? 'Vente rejetée par un responsable.', $op->conflicts ?? []);
        }

        $payload['origin'] = 'offline';
        $payload['device_id'] = $deviceId;
        $conflicts = $this->detectConflicts($payload, $user);

        if (empty($conflicts)) {
            try {
                [$sale] = $this->sales->create($this->toSaleData($payload), $user, null, true);
            } catch (BusinessException $e) {
                $conflicts[] = ['type' => 'business', 'message' => $e->getMessage()];
            }
        }

        $attrs = [
            'type' => 'sale',
            'device_id' => $deviceId,
            'user_id' => $user->id,
            'payload' => $payload,
            'created_offline_at' => isset($payload['sold_at']) ? Carbon::parse($payload['sold_at']) : null,
        ];

        try {
            if ($op) {
                $op->increment('attempts');
            } else {
                $op = SyncOperation::create($attrs + ['client_uuid' => $uuid, 'status' => 'conflict']);
            }
        } catch (QueryException) {
            $op = SyncOperation::query()->where('client_uuid', $uuid)->firstOrFail();
        }

        if (empty($conflicts) && isset($sale)) {
            $op->update(['status' => 'synced', 'conflicts' => null, 'message' => null, 'result_type' => $sale->getMorphClass(), 'result_id' => $sale->id]);

            return $this->result('synced', null, [], $sale);
        }

        $message = 'Vente en conflit : validation par un responsable requise.';
        $op->update(['status' => 'conflict', 'conflicts' => $conflicts, 'message' => $message]);

        return $this->result('conflict', $message, $conflicts);
    }

    /** Liste exhaustive des anomalies, présentées telles quelles au responsable. */
    public function detectConflicts(array $payload, User $user, bool $ignorePrices = false): array
    {
        $conflicts = [];
        if (! Settings::bool('offline_sales_enabled')) {
            $conflicts[] = ['type' => 'policy', 'message' => 'Les ventes hors ligne sont désactivées dans les paramètres.'];
        }
        if (! $user->hasPermission('sales.create')) {
            $conflicts[] = ['type' => 'permission', 'message' => "L'utilisateur n'est pas autorisé à vendre."];
        }
        if (! $user->openCashSession()) {
            $conflicts[] = ['type' => 'cash', 'message' => "Aucune caisse ouverte pour {$user->name} au moment de la synchronisation."];
        }

        $items = $payload['items'] ?? [];
        $products = Product::query()->whereIn('id', array_column($items, 'product_id'))->get()->keyBy('id');
        $needed = [];
        $total = Money::of(0);
        foreach ($items as $item) {
            $product = $products->get((int) ($item['product_id'] ?? 0));
            if (! $product) {
                $conflicts[] = ['type' => 'product', 'message' => 'Article supprimé ou inconnu (id '.($item['product_id'] ?? '?').').'];

                continue;
            }
            if (! $product->is_active) {
                $conflicts[] = ['type' => 'product', 'product_id' => $product->id, 'message' => "« {$product->name} » a été désactivé."];
            }
            if (! $ignorePrices && isset($item['unit_price']) && Money::cmp($item['unit_price'], $product->sale_price) !== 0) {
                $conflicts[] = [
                    'type' => 'price', 'product_id' => $product->id,
                    'message' => sprintf('Prix de « %s » modifié : ticket %s, catalogue %s.', $product->name, Money::format($item['unit_price']), Money::format($product->sale_price)),
                ];
            }
            if (! $product->isService()) {
                $needed[$product->id] = Money::add($needed[$product->id] ?? 0, $item['quantity'] ?? 0);
            }
            $total = $total->plus(Money::mul($item['quantity'] ?? 0, $item['unit_price'] ?? $product->sale_price));
        }
        if (! Settings::bool('allow_negative_stock')) {
            foreach ($needed as $productId => $qty) {
                $product = $products->get($productId);
                if (Money::cmp($qty, $product->stock_quantity) > 0) {
                    $conflicts[] = [
                        'type' => 'stock', 'product_id' => $productId,
                        'message' => sprintf('Stock insuffisant pour « %s » : vendu hors ligne %s, disponible %s.', $product->name, Money::formatQty($qty), Money::formatQty($product->stock_quantity)),
                    ];
                }
            }
        }
        $cap = Money::of(Settings::get('offline_max_sale_amount', '0'));
        if ($cap->isPositive() && Money::cmp($total, $cap) > 0) {
            $conflicts[] = ['type' => 'policy', 'message' => 'Montant supérieur au plafond autorisé hors ligne ('.Money::format($cap).').'];
        }

        return $conflicts;
    }

    /**
     * Décision d'un responsable sur une vente en conflit.
     *  - accept : la vente est créée aux prix du ticket (le client a déjà payé ce montant),
     *             rattachée à la caisse du vendeur si elle est ouverte, sinon à celle du responsable ;
     *  - reject : la vente est écartée ; l'argent éventuellement perçu doit être régularisé (remboursement ou apport).
     */
    public function resolve(SyncOperation $op, string $decision, User $resolver, ?string $note): SyncOperation
    {
        if ($op->status !== 'conflict') {
            throw new BusinessException('Cette opération n\'est plus en conflit.');
        }
        if ($decision === 'reject') {
            if (trim((string) $note) === '') {
                throw new BusinessException('Indiquez le motif du rejet.');
            }
            $op->update(['status' => 'rejected', 'message' => 'Rejetée : '.$note, 'resolved_by' => $resolver->id, 'resolved_at' => now(), 'resolution_note' => $note]);
            AuditService::log('sync.reject', $op, "Rejet de la vente hors ligne {$op->client_uuid} : {$note}");

            return $op;
        }

        $seller = $op->user;
        $session = $seller->openCashSession() ?? $resolver->openCashSession();
        if (! $session) {
            throw new BusinessException('Ouvrez une caisse (vendeur ou responsable) pour rattacher cette vente.');
        }
        [$sale] = $this->sales->create($this->toSaleData($op->payload), $seller, $session, true);
        $op->update([
            'status' => 'synced', 'result_type' => $sale->getMorphClass(), 'result_id' => $sale->id,
            'resolved_by' => $resolver->id, 'resolved_at' => now(), 'resolution_note' => $note,
            'message' => 'Acceptée par '.$resolver->name,
        ]);
        AuditService::log('sync.accept', $sale, "Vente hors ligne acceptée ({$sale->number}) malgré : ".collect($op->conflicts)->pluck('message')->implode(' | '));

        return $op;
    }

    public function touchDevice(string $deviceId, User $user, bool $sync = false): void
    {
        $device = Device::query()->firstOrNew(['device_id' => substr($deviceId, 0, 64)]);
        $device->user_id = $user->id;
        $device->user_agent = substr((string) request()->userAgent(), 0, 255);
        $device->last_seen_at = now();
        if ($sync) {
            $device->last_sync_at = now();
        }
        $device->save();
    }

    private function toSaleData(array $payload): array
    {
        return [
            'client_uuid' => $payload['client_uuid'],
            'customer_id' => $payload['customer_id'] ?? null,
            'items' => $payload['items'] ?? [],
            'payments' => $payload['payments'] ?? [],
            'notes' => $payload['notes'] ?? null,
            'origin' => 'offline',
            'device_id' => $payload['device_id'] ?? null,
            'sold_at' => $payload['sold_at'] ?? null,
        ];
    }

    private function result(string $status, ?string $message, array $conflicts, ?Sale $sale = null): array
    {
        return [
            'status' => $status,
            'message' => $message,
            'conflicts' => $conflicts,
            'sale_id' => $sale?->id,
            'sale_number' => $sale?->number,
        ];
    }
}
