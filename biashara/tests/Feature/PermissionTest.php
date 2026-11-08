<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Services\SaleService;
use Tests\TestCase;

class PermissionTest extends TestCase
{
    public function test_le_caissier_ne_peut_pas_gerer_le_catalogue_ni_les_rapports(): void
    {
        $cashier = $this->user(Role::CASHIER);
        $this->actingAs($cashier)->get(route('products.create'))->assertForbidden();
        $this->actingAs($cashier)->post(route('products.store'), ['reference' => 'X', 'name' => 'X'])->assertForbidden();
        $this->actingAs($cashier)->get(route('reports.index'))->assertForbidden();
        $this->actingAs($cashier)->get(route('settings.index'))->assertForbidden();
        $this->actingAs($cashier)->get(route('users.index'))->assertForbidden();
        $this->actingAs($cashier)->get(route('pos'))->assertOk();
    }

    public function test_le_magasinier_ne_peut_pas_vendre(): void
    {
        $keeper = $this->user(Role::STOREKEEPER);
        $product = $this->product([], '5');
        $this->actingAs($keeper)->get(route('pos'))->assertForbidden();
        $this->actingAs($keeper)->postJson(route('api.sales.store'), $this->salePayload([['product_id' => $product->id, 'quantity' => 1]]))->assertForbidden();
        $this->actingAs($keeper)->get(route('purchases.create'))->assertOk();
    }

    public function test_le_caissier_ne_voit_que_ses_ventes(): void
    {
        $a = $this->user();
        $b = $this->user();
        $this->openSession($a);
        $product = $this->product([], '5');
        [$sale] = app(SaleService::class)->create($this->salePayload([['product_id' => $product->id, 'quantity' => 1]], [['payment_method_id' => $this->cash()->id, 'amount' => '1000']]), $a);
        $this->actingAs($b)->get(route('sales.show', $sale))->assertForbidden();
        $this->actingAs($a)->get(route('sales.show', $sale))->assertOk();
    }

    public function test_permission_retiree_au_role_bloque_immediatement(): void
    {
        $manager = $this->user(Role::MANAGER);
        $this->actingAs($manager)->get(route('reports.index'))->assertOk();
        $role = Role::query()->where('slug', Role::MANAGER)->first();
        $role->permissions()->detach(Permission::query()->where('slug', 'reports.view')->value('id'));
        $this->actingAs($manager->fresh())->get(route('reports.index'))->assertForbidden();
    }

    public function test_un_compte_desactive_est_deconnecte(): void
    {
        $cashier = $this->user();
        $cashier->update(['is_active' => false]);
        $this->actingAs($cashier)->get(route('home'))->assertRedirect(route('login'));
    }

    public function test_connexion_et_mot_de_passe_hache(): void
    {
        $user = $this->user();
        $this->assertNotSame('password123', $user->getAttributes()['password']);
        $this->post(route('login.attempt'), ['username' => $user->username, 'password' => 'password123'])->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
    }
}
