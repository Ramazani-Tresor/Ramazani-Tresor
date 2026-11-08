<?php

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Models\Role;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\Tax;
use App\Services\SaleService;
use App\Services\StockService;
use App\Support\Money;
use Tests\TestCase;

class SaleTest extends TestCase
{
    public function test_une_vente_diminue_le_stock_et_cree_un_mouvement(): void
    {
        $cashier = $this->user();
        $this->openSession($cashier, '5000');
        $product = $this->product([], '10');

        [$sale] = app(SaleService::class)->create($this->salePayload(
            [['product_id' => $product->id, 'quantity' => 3]],
            [['payment_method_id' => $this->cash()->id, 'amount' => '5000']]
        ), $cashier);

        $this->assertSame('7.000', $product->fresh()->stock_quantity);
        $this->assertSame('3000.00', $sale->total);
        $this->assertSame('2000.00', $sale->change_given);
        $this->assertSame('0.00', $sale->amount_due);
        $mv = StockMovement::query()->where('type', 'sale')->where('source_id', $sale->id)->firstOrFail();
        $this->assertSame('-3.000', $mv->quantity);
        $this->assertSame('7.000', $mv->balance_after);
        $this->assertSame($cashier->id, $mv->user_id);
        $this->assertSame($sale->number, $mv->source_number);
        // Paiement espèces enregistré net de la monnaie rendue
        $this->assertSame('3000.00', Money::money($sale->payments()->sum('amount')));
    }

    public function test_un_service_se_vend_sans_mouvement_de_stock(): void
    {
        $cashier = $this->user();
        $this->openSession($cashier);
        $service = $this->product(['type' => 'service', 'sale_price' => '15000'], null);

        [$sale] = app(SaleService::class)->create($this->salePayload(
            [['product_id' => $service->id, 'quantity' => 2]],
            [['payment_method_id' => $this->mobile()->id, 'amount' => '30000']]
        ), $cashier);

        $this->assertSame('30000.00', $sale->total);
        $this->assertSame(0, StockMovement::query()->where('product_id', $service->id)->count());
        $this->assertSame('0.000', $service->fresh()->stock_quantity);
    }

    public function test_double_clic_ne_cree_pas_deux_ventes(): void
    {
        $cashier = $this->user();
        $this->openSession($cashier);
        $product = $this->product([], '10');
        $payload = $this->salePayload([['product_id' => $product->id, 'quantity' => 1]], [['payment_method_id' => $this->cash()->id, 'amount' => '1000']]);

        $this->actingAs($cashier)->postJson(route('api.sales.store'), $payload)->assertCreated();
        $this->actingAs($cashier)->postJson(route('api.sales.store'), $payload)->assertOk()->assertJson(['duplicate' => true]);

        $this->assertSame(1, Sale::query()->count());
        $this->assertSame('9.000', $product->fresh()->stock_quantity);
    }

    public function test_deux_caissiers_ne_peuvent_pas_vendre_le_meme_dernier_article(): void
    {
        $a = $this->user();
        $b = $this->user();
        $this->openSession($a);
        $this->openSession($b);
        $product = $this->product([], '1');

        $this->actingAs($a)->postJson(route('api.sales.store'), $this->salePayload([['product_id' => $product->id, 'quantity' => 1]], [['payment_method_id' => $this->cash()->id, 'amount' => '1000']]))->assertCreated();
        $this->actingAs($b)->postJson(route('api.sales.store'), $this->salePayload([['product_id' => $product->id, 'quantity' => 1]], [['payment_method_id' => $this->cash()->id, 'amount' => '1000']]))
            ->assertStatus(422)->assertJsonFragment(['message' => 'Stock insuffisant pour « '.$product->name.' » : disponible 0, demandé 1.']);

        $this->assertSame('0.000', $product->fresh()->stock_quantity);
        $this->assertSame(1, Sale::query()->count());
    }

    public function test_les_anciens_recus_restent_inchanges_apres_modification_du_prix(): void
    {
        $cashier = $this->user();
        $this->openSession($cashier);
        $product = $this->product(['name' => 'Savon original', 'sale_price' => '1000'], '5');
        [$sale] = app(SaleService::class)->create($this->salePayload([['product_id' => $product->id, 'quantity' => 1]], [['payment_method_id' => $this->cash()->id, 'amount' => '1000']]), $cashier);

        $product->update(['name' => 'Savon renommé', 'sale_price' => '1500']);

        $item = $sale->items()->first();
        $this->assertSame('Savon original', $item->product_name);
        $this->assertSame('1000.00', $item->unit_price);
        $this->actingAs($cashier)->get(route('sales.receipt', $sale))->assertOk()->assertSee('Savon original')->assertDontSee('Savon renommé');
    }

    public function test_paiement_insuffisant_refuse_sans_droit_credit(): void
    {
        $cashier = $this->user();
        $this->openSession($cashier);
        $product = $this->product([], '5');
        $this->expectException(BusinessException::class);
        app(SaleService::class)->create($this->salePayload([['product_id' => $product->id, 'quantity' => 2]], [['payment_method_id' => $this->cash()->id, 'amount' => '500']]), $cashier);
    }

    public function test_remise_refusee_sans_permission(): void
    {
        $cashier = $this->user();
        $this->openSession($cashier);
        $product = $this->product([], '5');
        $this->actingAs($cashier)->postJson(route('api.sales.store'), $this->salePayload(
            [['product_id' => $product->id, 'quantity' => 1, 'discount_amount' => '100']],
            [['payment_method_id' => $this->cash()->id, 'amount' => '1000']]
        ))->assertStatus(422)->assertJsonFragment(['message' => "Vous n'êtes pas autorisé à accorder des remises."]);
        $this->assertSame('5.000', $product->fresh()->stock_quantity);
    }

    public function test_remise_et_taxe_calculees_en_decimal_exact(): void
    {
        $manager = $this->user(Role::MANAGER);
        $this->openSession($manager);
        $tax = Tax::create(['name' => 'TVA test', 'rate' => '16', 'is_active' => true]);
        $product = $this->product(['sale_price' => '333.33', 'tax_id' => $tax->id], '10');

        [$sale] = app(SaleService::class)->create($this->salePayload(
            [['product_id' => $product->id, 'quantity' => 3, 'discount_amount' => '99.99']],
            [['payment_method_id' => $this->cash()->id, 'amount' => '1044']]
        ), $manager);

        // 3 × 333,33 = 999,99 ; − 99,99 = 900,00 ; taxe 16 % = 144,00 ; total 1 044,00
        $this->assertSame('999.99', $sale->subtotal);
        $this->assertSame('99.99', $sale->discount_total);
        $this->assertSame('144.00', $sale->tax_total);
        $this->assertSame('1044.00', $sale->total);
        $this->assertSame('0.00', $sale->amount_due);
    }

    public function test_vente_impossible_sans_caisse_ouverte(): void
    {
        $cashier = $this->user();
        $product = $this->product([], '5');
        $this->actingAs($cashier)->postJson(route('api.sales.store'), $this->salePayload([['product_id' => $product->id, 'quantity' => 1]], [['payment_method_id' => $this->cash()->id, 'amount' => '1000']]))
            ->assertStatus(422);
        $this->assertSame(0, Sale::query()->count());
    }

    public function test_le_stock_correspond_toujours_aux_mouvements(): void
    {
        $cashier = $this->user();
        $this->openSession($cashier);
        $product = $this->product([], '20');
        for ($i = 0; $i < 3; $i++) {
            app(SaleService::class)->create($this->salePayload([['product_id' => $product->id, 'quantity' => 2]], [['payment_method_id' => $this->cash()->id, 'amount' => '2000']]), $cashier);
        }
        $this->assertSame('14.000', $product->fresh()->stock_quantity);
        $this->assertSame('14.000', app(StockService::class)->recomputedQuantity($product));
        $this->artisan('app:check-stock')->assertExitCode(0);
    }
}
