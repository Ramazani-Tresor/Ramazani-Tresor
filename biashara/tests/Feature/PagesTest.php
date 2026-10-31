<?php

namespace Tests\Feature;

use App\Models\CashSession;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Supplier;
use App\Models\User;
use App\Services\InventoryService;
use Database\Seeders\DemoSeeder;
use Tests\TestCase;

/** Chaque écran s'affiche sans erreur, pour chaque rôle, avec les données de démonstration. */
class PagesTest extends TestCase
{
    public function test_tous_les_ecrans_s_affichent(): void
    {
        $this->seed(DemoSeeder::class);
        $admin = User::query()->where('username', 'admin')->first();
        app(InventoryService::class)->open($admin, null, null);

        $sale = Sale::query()->first();
        $urls = [
            route('home'), route('dashboard'), route('remote'), route('account'),
            route('categories.index'), route('categories.create'), route('categories.edit', 1),
            route('products.index'), route('products.create'), route('products.import'), route('products.show', Product::first()), route('products.edit', Product::first()),
            route('suppliers.index'), route('suppliers.create'), route('suppliers.show', Supplier::first()),
            route('purchases.index'), route('purchases.create'), route('purchases.show', Purchase::first()), route('purchases.print', Purchase::first()),
            route('stock.index'), route('stock.movements'), route('stock.exit'),
            route('pos'), route('api.pos.catalog'),
            route('sales.index'), route('sales.show', $sale), route('sales.receipt', $sale), route('returns.create', $sale),
            route('returns.index'), route('returns.show', SaleReturn::first()),
            route('customers.index'), route('customers.create'), route('customers.show', Customer::first()), route('customers.statement', Customer::first()),
            route('cash.index'), route('cash.sessions'), route('cash.show', CashSession::first()),
            route('expenses.index'), route('expenses.create'), route('expenses.show', Expense::first()),
            route('inventories.index'), route('inventories.create'), route('inventories.show', Inventory::first()),
            route('reports.index'), route('sync.index'), route('users.index'), route('users.create'), route('users.edit', $admin),
            route('roles.index'), route('settings.index'), route('audit.index'),
            route('pwa.manifest'), route('pwa.sw'), route('products.import.template'),
        ];
        foreach ($urls as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
        $this->actingAs($admin)->get(route('inventories.export', Inventory::first()))->assertOk();

        foreach (['gerant', 'caissier', 'magasinier'] as $username) {
            $u = User::query()->where('username', $username)->first();
            $this->actingAs($u)->get(route('home'))->assertOk();
            $this->actingAs($u)->get(route('dashboard'))->assertOk();
        }
        $cashier = User::query()->where('username', 'caissier')->first();
        $this->actingAs($cashier)->get(route('pos'))->assertOk();
        $this->actingAs($cashier)->get(route('sales.index'))->assertOk();
        $this->actingAs($cashier)->get(route('cash.index'))->assertRedirect();
    }

    public function test_page_de_connexion(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Connexion');
        $this->get(route('home'))->assertRedirect(route('login'));
    }
}
