<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Sale;
use App\Models\SyncOperation;
use App\Support\Money;
use Illuminate\Support\Str;
use Tests\TestCase;

class OfflineSyncTest extends TestCase
{
    private function offlineSale($product, $qty = 1, $price = '1000.00'): array
    {
        return [
            'client_uuid' => (string) Str::uuid(),
            'items' => [['product_id' => $product->id, 'quantity' => $qty, 'unit_price' => $price]],
            'payments' => [['payment_method_id' => $this->cash()->id, 'amount' => bcmul_s($price, $qty)]],
            'sold_at' => now()->subHour()->toIso8601String(),
        ];
    }

    public function test_une_vente_hors_ligne_n_est_synchronisee_qu_une_seule_fois(): void
    {
        $cashier = $this->user();
        $this->openSession($cashier);
        $product = $this->product([], '10');
        $sale = $this->offlineSale($product, 2);

        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($cashier)->postJson(route('api.sync.sales'), ['device_id' => 'dev-1', 'sales' => [$sale]])
                ->assertOk()->assertJsonPath('results.0.status', 'synced');
        }
        $this->assertSame(1, Sale::query()->count());
        $this->assertSame('8.000', $product->fresh()->stock_quantity);
        $this->assertSame('offline', Sale::query()->first()->origin);
    }

    public function test_un_prix_modifie_produit_un_conflit_sans_modification_silencieuse(): void
    {
        $cashier = $this->user();
        $manager = $this->user(Role::MANAGER);
        $this->openSession($cashier);
        $product = $this->product(['sale_price' => '1200'], '10'); // le ticket hors ligne indique 1000
        $sale = $this->offlineSale($product, 1, '1000.00');

        $r = $this->actingAs($cashier)->postJson(route('api.sync.sales'), ['device_id' => 'dev-1', 'sales' => [$sale]]);
        $r->assertJsonPath('results.0.status', 'conflict');
        $this->assertSame(0, Sale::query()->count());
        $op = SyncOperation::query()->sole();
        $this->assertSame('price', $op->conflicts[0]['type']);

        // Le responsable accepte au prix du ticket (montant réellement payé par le client)
        $this->actingAs($manager)->post(route('sync.resolve', $op), ['decision' => 'accept', 'note' => 'Client déjà parti'])->assertRedirect();
        $this->assertSame('synced', $op->fresh()->status);
        $this->assertSame('1000.00', Sale::query()->sole()->total);

        // Une nouvelle tentative de l'appareil ne crée rien de plus
        $this->actingAs($cashier)->postJson(route('api.sync.sales'), ['device_id' => 'dev-1', 'sales' => [$sale]])->assertJsonPath('results.0.status', 'synced');
        $this->assertSame(1, Sale::query()->count());
    }

    public function test_stock_insuffisant_a_la_synchronisation_puis_rejet(): void
    {
        $cashier = $this->user();
        $manager = $this->user(Role::MANAGER);
        $this->openSession($cashier);
        $product = $this->product([], '1');
        $sale = $this->offlineSale($product, 3);

        $this->actingAs($cashier)->postJson(route('api.sync.sales'), ['device_id' => 'dev-2', 'sales' => [$sale]])->assertJsonPath('results.0.status', 'conflict');
        $op = SyncOperation::query()->sole();
        $this->actingAs($manager)->post(route('sync.resolve', $op), ['decision' => 'reject', 'note' => 'Marchandise indisponible, client remboursé'])->assertRedirect();
        $this->assertSame('rejected', $op->fresh()->status);

        $this->actingAs($cashier)->postJson(route('api.sync.sales'), ['device_id' => 'dev-2', 'sales' => [$sale]])->assertJsonPath('results.0.status', 'rejected');
        $this->assertSame(0, Sale::query()->count());
        $this->assertSame('1.000', $product->fresh()->stock_quantity);
    }
}

function bcmul_s(string $a, $b): string
{
    return Money::money(Money::mul($a, $b));
}
