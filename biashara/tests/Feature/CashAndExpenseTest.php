<?php

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Models\ExpenseCategory;
use App\Models\Role;
use App\Services\CashService;
use App\Services\ExpenseService;
use App\Services\SaleService;
use Tests\TestCase;

class CashAndExpenseTest extends TestCase
{
    public function test_cloture_de_caisse_calcule_l_ecart(): void
    {
        $cashier = $this->user();
        $cash = app(CashService::class);
        $session = $this->openSession($cashier, '10000');
        $product = $this->product(['sale_price' => '2500'], '20');

        app(SaleService::class)->create($this->salePayload([['product_id' => $product->id, 'quantity' => 2]], [['payment_method_id' => $this->cash()->id, 'amount' => '10000']]), $cashier); // 5 000 + monnaie 5 000
        app(SaleService::class)->create($this->salePayload([['product_id' => $product->id, 'quantity' => 4]], [['payment_method_id' => $this->mobile()->id, 'amount' => '10000']]), $cashier); // électronique

        $this->assertSame('15000.00', $cash->expectedCash($session));
        $this->assertSame('10000.00', $cash->electronicTotal($session));

        try {
            $cash->close($session, '14500', '', $cashier);
            $this->fail('Un écart sans justification doit être refusé');
        } catch (BusinessException) {
        }
        $closed = $cash->close($session, '14500', 'Erreur de rendu de monnaie', $cashier);
        $this->assertSame('15000.00', $closed->expected_cash);
        $this->assertSame('-500.00', $closed->difference);
        $this->assertSame('10000.00', $closed->electronic_total);
    }

    public function test_aucune_operation_sur_une_session_cloturee(): void
    {
        $cashier = $this->user();
        $session = $this->openSession($cashier, '0');
        app(CashService::class)->close($session, '0', null, $cashier);
        $product = $this->product([], '5');
        $this->expectException(BusinessException::class);
        app(SaleService::class)->create($this->salePayload([['product_id' => $product->id, 'quantity' => 1]], [['payment_method_id' => $this->cash()->id, 'amount' => '1000']]), $cashier, $session);
    }

    public function test_une_seule_session_ouverte_par_caissier(): void
    {
        $cashier = $this->user();
        $this->openSession($cashier);
        $this->expectException(BusinessException::class);
        $this->openSession($cashier);
    }

    public function test_la_depense_n_affecte_la_caisse_que_si_payee_depuis_la_caisse(): void
    {
        $manager = $this->user(Role::MANAGER);
        $session = $this->openSession($manager, '20000');
        $cat = ExpenseCategory::query()->first();
        $svc = app(ExpenseService::class);
        $base = ['expense_category_id' => $cat->id, 'expense_date' => now()->toDateString(), 'beneficiary' => 'Bailleur fictif', 'reason' => 'Loyer'];

        $svc->create($base + ['amount' => '5000', 'payment_method_id' => $this->mobile()->id, 'paid_from_cash' => false], $manager);
        $this->assertSame('20000.00', app(CashService::class)->expectedCash($session));

        $svc->create($base + ['amount' => '3000', 'payment_method_id' => $this->cash()->id, 'paid_from_cash' => true], $manager);
        $this->assertSame('17000.00', app(CashService::class)->expectedCash($session));
    }

    public function test_depense_d_un_caissier_en_attente_sans_effet_sur_la_caisse(): void
    {
        $cashier = $this->user();
        $session = $this->openSession($cashier, '20000');
        $expense = app(ExpenseService::class)->create([
            'expense_category_id' => ExpenseCategory::query()->value('id'), 'expense_date' => now()->toDateString(),
            'beneficiary' => 'Transporteur', 'reason' => 'Livraison', 'amount' => '2000',
            'payment_method_id' => $this->cash()->id, 'paid_from_cash' => true,
        ], $cashier);
        $this->assertSame('pending', $expense->status);
        $this->assertSame('20000.00', app(CashService::class)->expectedCash($session));
    }
}
