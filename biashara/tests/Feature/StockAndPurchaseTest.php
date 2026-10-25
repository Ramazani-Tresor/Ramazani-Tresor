<?php

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Services\InventoryService;
use App\Services\PurchaseService;
use App\Services\SaleService;
use App\Services\StockService;
use Tests\TestCase;

class StockAndPurchaseTest extends TestCase
{
    public function test_le_stock_initial_est_un_mouvement_identifiable(): void
    {
        $product = $this->product(['cost_price' => '500'], '12');
        $mv = StockMovement::query()->where('product_id', $product->id)->sole();
        $this->assertSame('initial', $mv->type);
        $this->assertSame('12.000', $mv->quantity);
        $this->assertSame('500.0000', $product->fresh()->average_cost);
    }

    public function test_un_approvisionnement_augmente_le_stock_et_met_a_jour_le_cmp(): void
    {
        $keeper = $this->user(Role::MANAGER);
        $product = $this->product(['cost_price' => '600'], '10', $keeper);
        $supplier = Supplier::create(['name' => 'Fournisseur fictif', 'is_active' => true]);
        $service = app(PurchaseService::class);

        $purchase = $service->saveDraft(null, ['supplier_id' => $supplier->id, 'supplier_reference' => 'FAC-01', 'purchase_date' => now()->toDateString()],
            [['product_id' => $product->id, 'quantity' => '10', 'unit_cost' => '800']], $keeper);
        $this->assertSame('10.000', $product->fresh()->stock_quantity, 'Un brouillon ne modifie pas le stock');

        $service->validate($purchase, $keeper);
        $product->refresh();
        $this->assertSame('20.000', $product->stock_quantity);
        // CMP = (10 × 600 + 10 × 800) / 20 = 700
        $this->assertSame('700.0000', $product->average_cost);
        $this->assertSame('8000.00', $purchase->fresh()->total);

        $this->expectException(BusinessException::class);
        $service->validate($purchase, $keeper); // pas de double validation
    }

    public function test_la_vente_conserve_le_cout_de_sortie(): void
    {
        $cashier = $this->user();
        $this->openSession($cashier);
        $product = $this->product(['cost_price' => '600'], '10');
        [$sale] = app(SaleService::class)->create($this->salePayload([['product_id' => $product->id, 'quantity' => 1]], [['payment_method_id' => $this->cash()->id, 'amount' => '1000']]), $cashier);
        $this->assertSame('600.0000', $sale->items()->first()->unit_cost);
    }

    public function test_stock_negatif_interdit_par_defaut_sur_sortie_manuelle(): void
    {
        $product = $this->product([], '2');
        $this->expectException(BusinessException::class);
        app(StockService::class)->manualExit($product, 'loss', '3', 'Casse', $this->user(Role::MANAGER));
    }

    public function test_perte_enregistree_avec_motif_et_utilisateur(): void
    {
        $manager = $this->user(Role::MANAGER);
        $product = $this->product([], '5');
        $mv = app(StockService::class)->manualExit($product, 'loss', '2', 'Bouteilles cassées', $manager);
        $this->assertSame('-2.000', $mv->quantity);
        $this->assertSame('Bouteilles cassées', $mv->reason);
        $this->assertSame($manager->id, $mv->user_id);
        $this->assertSame('3.000', $product->fresh()->stock_quantity);
    }

    public function test_inventaire_valide_genere_un_ajustement_tracable_meme_avec_ventes_apres_releve(): void
    {
        $manager = $this->user(Role::MANAGER);
        $this->openSession($manager);
        $product = $this->product([], '10');
        $svc = app(InventoryService::class);

        $inventory = $svc->open($manager, null, null);
        $item = $inventory->items()->where('product_id', $product->id)->sole();

        // Relevé : 8 comptés pour 10 théoriques → écart −2
        $svc->recordCounts($inventory, [$item->id => ['counted_quantity' => '8', 'justification' => 'Vol constaté']], $manager);
        // Vente de 3 APRÈS le relevé, avant validation
        app(SaleService::class)->create($this->salePayload([['product_id' => $product->id, 'quantity' => 3]], [['payment_method_id' => $this->cash()->id, 'amount' => '3000']]), $manager);
        $this->assertSame('7.000', $product->fresh()->stock_quantity);

        $svc->validate($inventory, $manager);

        // 10 − 3 (vente) − 2 (écart) = 5 : la vente postérieure au relevé n'est pas écrasée
        $this->assertSame('5.000', $product->fresh()->stock_quantity);
        $adj = StockMovement::query()->where('type', 'inventory_adjustment')->sole();
        $this->assertSame('-2.000', $adj->quantity);
        $this->assertSame($inventory->number, $adj->source_number);
        $this->assertStringContainsString('Vol constaté', $adj->reason);
        $this->assertSame('validated', $inventory->fresh()->status);
    }

    public function test_un_ecart_non_justifie_bloque_la_validation(): void
    {
        $manager = $this->user(Role::MANAGER);
        $product = $this->product([], '10');
        $svc = app(InventoryService::class);
        $inventory = $svc->open($manager, null, null);
        $item = $inventory->items()->where('product_id', $product->id)->sole();
        $svc->recordCounts($inventory, [$item->id => ['counted_quantity' => '9']], $manager);
        $this->expectException(BusinessException::class);
        $svc->validate($inventory, $manager);
    }
}
