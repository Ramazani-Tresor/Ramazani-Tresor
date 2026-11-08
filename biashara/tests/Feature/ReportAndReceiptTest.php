<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\ExpenseCategory;
use App\Models\Role;
use App\Services\ExpenseService;
use App\Services\FinancialMetrics;
use App\Services\ReportService;
use App\Services\ReturnService;
use App\Services\SaleService;
use App\Support\Money;
use Tests\TestCase;

class ReportAndReceiptTest extends TestCase
{
    public function test_les_indicateurs_correspondent_aux_transactions(): void
    {
        $manager = $this->user(Role::MANAGER);
        $this->openSession($manager, '50000');
        $p = $this->product(['cost_price' => '600', 'sale_price' => '1000'], '20');
        $customer = Customer::create(['code' => 'CLI-R', 'name' => 'Client rapport', 'is_active' => true]);
        $sales = app(SaleService::class);

        [$s1] = $sales->create($this->salePayload([['product_id' => $p->id, 'quantity' => 5]], [['payment_method_id' => $this->cash()->id, 'amount' => '5000']]), $manager);
        $sales->create($this->salePayload([['product_id' => $p->id, 'quantity' => 3]], [['payment_method_id' => $this->mobile()->id, 'amount' => '1000']], ['customer_id' => $customer->id]), $manager);
        app(ReturnService::class)->create($s1, [['sale_item_id' => $s1->items()->first()->id, 'quantity' => 1, 'condition' => 'resaleable']], 'Retour', $manager, $manager);
        app(ExpenseService::class)->create(['expense_category_id' => ExpenseCategory::query()->value('id'), 'expense_date' => now()->toDateString(), 'amount' => '1500',
            'beneficiary' => 'X', 'reason' => 'Transport', 'payment_method_id' => $this->cash()->id, 'paid_from_cash' => true], $manager);

        $m = app(FinancialMetrics::class)->compute(now()->startOfDay(), now()->endOfDay());
        $this->assertSame('7000.00', $m['revenue_ttc']);    // 8 000 − 1 000
        $this->assertSame('4200.00', $m['cogs']);           // 7 × 600
        $this->assertSame('2800.00', $m['margin']);         // 7 000 − 4 200 (dépenses NON déduites)
        $this->assertSame('1500.00', $m['expenses']);
        $this->assertSame('1300.00', $m['estimated_result']);
        $this->assertSame('5000.00', $m['net_collected']);  // 5 000 + 1 000 − 1 000 remboursé
        $this->assertSame('2000.00', $m['receivables']);

        $report = app(ReportService::class)->build('sales_summary', ['from' => now()->startOfDay(), 'to' => now()->endOfDay()]);
        $this->assertSame('8000.00', Money::money($report['totals']['total']));
    }

    public function test_recu_lisible_dans_les_trois_formats_avec_duplicata(): void
    {
        $manager = $this->user(Role::MANAGER);
        $this->openSession($manager);
        $p = $this->product(['name' => 'Riz fictif 25 kg'], '5');
        [$sale] = app(SaleService::class)->create($this->salePayload([['product_id' => $p->id, 'quantity' => 1]], [['payment_method_id' => $this->cash()->id, 'amount' => '1000']]), $manager);

        $first = $this->actingAs($manager)->get(route('sales.receipt', [$sale, 'format' => '80']));
        $first->assertOk()->assertSee('Riz fictif 25 kg')->assertSee($sale->number)->assertDontSee('DUPLICATA');
        $this->actingAs($manager)->get(route('sales.receipt', [$sale, 'format' => '58']))->assertOk()->assertSee('DUPLICATA')->assertSee('receipt-58', false);
        $this->actingAs($manager)->get(route('sales.receipt', [$sale, 'format' => 'a4']))->assertOk()->assertSee('receipt-a4', false);
        $this->actingAs($manager)->get(route('sales.pdf', [$sale, 'format' => 'a4']))->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_exports_des_rapports(): void
    {
        $manager = $this->user(Role::MANAGER);
        $this->product([], '5');
        foreach (array_keys(ReportService::REPORTS) as $type) {
            $this->actingAs($manager)->get(route('reports.show', $type))->assertOk();
        }
        $this->actingAs($manager)->get(route('reports.export', ['stock', 'xlsx']))->assertOk();
        $this->actingAs($manager)->get(route('reports.export', ['stock', 'csv']))->assertOk();
        $this->actingAs($manager)->get(route('reports.export', ['stock', 'pdf']))->assertOk();
    }
}
