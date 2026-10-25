<?php

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Models\Customer;
use App\Models\CustomerSettlement;
use App\Models\Role;
use App\Models\Sale;
use App\Services\CashService;
use App\Services\ReturnService;
use App\Services\SaleService;
use App\Services\SettlementService;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReturnsAndCreditTest extends TestCase
{
    private function sell($user, $product, $qty, $paid, $customer = null)
    {
        return app(SaleService::class)->create($this->salePayload(
            [['product_id' => $product->id, 'quantity' => $qty]],
            $paid > 0 ? [['payment_method_id' => $this->cash()->id, 'amount' => (string) $paid]] : [],
            ['customer_id' => $customer?->id]
        ), $user)[0];
    }

    public function test_retour_partiel_respecte_les_quantites_vendues(): void
    {
        $manager = $this->user(Role::MANAGER);
        $this->openSession($manager, '10000');
        $product = $this->product([], '10');
        $sale = $this->sell($manager, $product, 5, 5000);
        $item = $sale->items()->first();
        $returns = app(ReturnService::class);

        $returns->create($sale, [['sale_item_id' => $item->id, 'quantity' => 2, 'condition' => 'resaleable']], 'Taille incorrecte', $manager, $manager);
        $this->assertSame('7.000', $product->fresh()->stock_quantity);
        $this->assertSame('2.000', $item->fresh()->returned_quantity);
        $this->assertSame('2000.00', $sale->fresh()->refunded_total);

        try {
            $returns->create($sale, [['sale_item_id' => $item->id, 'quantity' => 4, 'condition' => 'resaleable']], 'Encore', $manager, $manager);
            $this->fail('Un retour supérieur au reliquat doit être refusé');
        } catch (BusinessException $e) {
            $this->assertStringContainsString('maximum 3', $e->getMessage());
        }
        $this->assertSame('7.000', $product->fresh()->stock_quantity);
    }

    public function test_article_deteriore_ne_rejoint_pas_le_stock_vendable(): void
    {
        $manager = $this->user(Role::MANAGER);
        $this->openSession($manager, '10000');
        $product = $this->product([], '10');
        $sale = $this->sell($manager, $product, 3, 3000);

        app(ReturnService::class)->create($sale, [['sale_item_id' => $sale->items()->first()->id, 'quantity' => 1, 'condition' => 'damaged']], 'Produit abîmé', $manager, $manager);

        $product->refresh();
        $this->assertSame('7.000', $product->stock_quantity);
        $this->assertSame('1.000', $product->damaged_quantity);
    }

    public function test_motif_obligatoire(): void
    {
        $manager = $this->user(Role::MANAGER);
        $this->openSession($manager, '10000');
        $product = $this->product([], '10');
        $sale = $this->sell($manager, $product, 1, 1000);
        $this->expectException(BusinessException::class);
        app(ReturnService::class)->create($sale, [['sale_item_id' => $sale->items()->first()->id, 'quantity' => 1, 'condition' => 'resaleable']], '  ', $manager, $manager);
    }

    public function test_reglement_partiel_laisse_une_creance_exacte_et_nest_pas_compte_deux_fois(): void
    {
        $manager = $this->user(Role::MANAGER);
        $this->openSession($manager);
        $customer = Customer::create(['code' => 'CLI-T1', 'name' => 'Client fictif', 'is_active' => true]);
        $product = $this->product(['sale_price' => '2500'], '10');

        $sale = $this->sell($manager, $product, 4, 3000, $customer); // total 10 000, payé 3 000
        $this->assertSame('7000.00', $sale->amount_due);
        $this->assertSame('7000', (string) (int) $customer->balanceDue());

        $uuid = (string) Str::uuid();
        $svc = app(SettlementService::class);
        $svc->settle($customer, '2500', $this->cash(), $manager, $uuid);
        $svc->settle($customer, '2500', $this->cash(), $manager, $uuid); // re-soumission : ignorée

        $this->assertSame(1, CustomerSettlement::query()->count());
        $sale->refresh();
        $this->assertSame('4500.00', $sale->amount_due);
        $this->assertSame('5500.00', $sale->amount_paid);
        $this->assertSame(1, Sale::query()->count(), 'Un règlement n\'est pas une nouvelle vente');

        $this->expectException(BusinessException::class);
        $svc->settle($customer, '5000', $this->cash(), $manager, (string) Str::uuid()); // > solde
    }

    public function test_vente_a_credit_exige_un_client(): void
    {
        $manager = $this->user(Role::MANAGER);
        $this->openSession($manager);
        $product = $this->product([], '10');
        $this->expectException(BusinessException::class);
        $this->sell($manager, $product, 2, 500);
    }

    public function test_retour_sur_vente_a_credit_eteint_d_abord_la_creance(): void
    {
        $manager = $this->user(Role::MANAGER);
        $this->openSession($manager, '10000');
        $customer = Customer::create(['code' => 'CLI-T2', 'name' => 'Client crédit', 'is_active' => true]);
        $product = $this->product([], '10');
        $sale = $this->sell($manager, $product, 4, 1000, $customer); // total 4 000, dû 3 000

        $ret = app(ReturnService::class)->create($sale, [['sale_item_id' => $sale->items()->first()->id, 'quantity' => 2, 'condition' => 'resaleable']], 'Surplus', $manager, $manager);
        $this->assertSame('2000.00', $ret->due_reduction);
        $this->assertSame('0.00', $ret->refund_amount);
        $this->assertSame('1000.00', $sale->fresh()->amount_due);
    }

    public function test_annulation_genere_les_operations_correctrices(): void
    {
        $manager = $this->user(Role::MANAGER);
        $session = $this->openSession($manager, '1000');
        $product = $this->product([], '10');
        $sale = $this->sell($manager, $product, 3, 3000);

        app(SaleService::class)->cancel($sale, 'Erreur de saisie', $manager);

        $sale->refresh();
        $this->assertSame('cancelled', $sale->status);
        $this->assertNotNull($sale->fresh());
        $this->assertSame('10.000', $product->fresh()->stock_quantity);
        $this->assertSame('3000.00', $sale->refunded_total);
        $this->assertSame('1000.00', app(CashService::class)->expectedCash($session));
        $this->assertDatabaseHas('stock_movements', ['type' => 'sale_cancel', 'product_id' => $product->id]);
    }
}
