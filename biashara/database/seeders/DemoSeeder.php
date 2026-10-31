<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Customer;
use App\Models\ExpenseCategory;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\CashService;
use App\Services\ExpenseService;
use App\Services\NumberingService;
use App\Services\ProductService;
use App\Services\PurchaseService;
use App\Services\ReturnService;
use App\Services\SaleService;
use App\Services\SettlementService;
use App\Support\Money;
use App\Support\Settings;
use Brick\Math\RoundingMode;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * DONNÉES DE DÉMONSTRATION ENTIÈREMENT FICTIVES.
 * Noms de clients, fournisseurs, articles et montants inventés, à ne pas utiliser en production.
 * Toutes les opérations passent par les services métier : stock, caisse et mouvements restent cohérents.
 *
 *   php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DatabaseSeeder::class);
        Settings::set([
            'shop_name' => 'Boutique Démo Kasapa (fictive)',
            'shop_address' => 'Avenue Exemple n° 1, Lubumbashi (adresse fictive)',
            'shop_phone' => '+243 000 000 000',
            'secondary_currency_enabled' => '1',
        ]);

        $pwd = 'demo1234';
        $roleId = fn ($s) => Role::query()->where('slug', $s)->value('id');
        $manager = User::query()->firstOrCreate(['username' => 'gerant'], ['name' => 'Grâce Gérante (démo)', 'password' => $pwd, 'role_id' => $roleId(Role::MANAGER)]);
        $cashier = User::query()->firstOrCreate(['username' => 'caissier'], ['name' => 'Camille Caissière (démo)', 'password' => $pwd, 'role_id' => $roleId(Role::CASHIER)]);
        $cashier2 = User::query()->firstOrCreate(['username' => 'caissier2'], ['name' => 'Jonas Caissier (démo)', 'password' => $pwd, 'role_id' => $roleId(Role::CASHIER)]);
        $keeper = User::query()->firstOrCreate(['username' => 'magasinier'], ['name' => 'Marc Magasinier (démo)', 'password' => $pwd, 'role_id' => $roleId(Role::STOREKEEPER)]);

        $start = now()->subDays(20)->setTime(8, 0);
        Carbon::setTestNow($start);

        $cats = [];
        foreach ([['Alimentation', '#1E6FD9'], ['Boissons', '#0EA5E9'], ['Hygiène', '#14B8A6'], ['Quincaillerie', '#6366F1'], ['Services', '#0B1F3A']] as [$n, $c]) {
            $cats[$n] = Category::query()->firstOrCreate(['name' => $n], ['color' => $c, 'is_active' => true]);
        }
        $unit = fn ($s) => Unit::query()->where('symbol', $s)->value('id');
        $products = [];
        $catalog = [
            ['ALI-001', 'Farine de maïs 25 kg', 'Alimentation', 'sac', '28000', '33500', 60, 10, '6001234500011'],
            ['ALI-002', 'Riz parfumé 5 kg', 'Alimentation', 'sac', '14500', '18000', 80, 15, '6001234500028'],
            ['ALI-003', 'Huile végétale 1 L', 'Alimentation', 'L', '4200', '5500', 120, 20, '6001234500035'],
            ['ALI-004', 'Sucre en poudre 1 kg', 'Alimentation', 'kg', '2600', '3300', 90, 20, '6001234500042'],
            ['ALI-005', 'Haricots secs (vrac)', 'Alimentation', 'kg', '3000', '4000', 50, 10, null],
            ['BOI-001', 'Eau minérale 1,5 L', 'Boissons', 'pce', '900', '1500', 200, 48, '6001234500059'],
            ['BOI-002', 'Jus de fruits 1 L', 'Boissons', 'pce', '2800', '3800', 70, 12, '6001234500066'],
            ['BOI-003', 'Boisson gazeuse 33 cl', 'Boissons', 'pce', '700', '1200', 6, 24, '6001234500073'],
            ['HYG-001', 'Savon de ménage', 'Hygiène', 'pce', '800', '1200', 150, 30, '6001234500080'],
            ['HYG-002', 'Dentifrice 100 ml', 'Hygiène', 'pce', '2100', '3000', 40, 10, '6001234500097'],
            ['HYG-003', 'Lessive en poudre 1 kg', 'Hygiène', 'pce', '4500', '6000', 3, 8, '6001234500103'],
            ['QUI-001', 'Ampoule LED 9 W', 'Quincaillerie', 'pce', '3500', '5000', 35, 10, '6001234500110'],
            ['QUI-002', 'Rallonge électrique 3 m', 'Quincaillerie', 'pce', '9000', '13000', 12, 4, '6001234500127'],
        ];
        $ps = app(ProductService::class);
        foreach ($catalog as [$ref, $name, $cat, $u, $cost, $price, $stock, $alert, $barcode]) {
            $products[$ref] = Product::query()->where('reference', $ref)->first() ?? $ps->create([
                'reference' => $ref, 'name' => $name, 'type' => 'product', 'category_id' => $cats[$cat]->id, 'unit_id' => $unit($u),
                'cost_price' => $cost, 'sale_price' => $price, 'alert_threshold' => $alert, 'barcode' => $barcode, 'is_active' => true,
                'description' => 'Article fictif de démonstration.',
            ], (string) $stock, $keeper);
        }
        foreach ([['SRV-001', 'Livraison à domicile', '0', '3000', 'forf.'], ['SRV-002', 'Recharge téléphone (service)', '0', '1000', 'forf.']] as [$ref, $name, $cost, $price, $u]) {
            $products[$ref] = Product::query()->where('reference', $ref)->first() ?? $ps->create([
                'reference' => $ref, 'name' => $name, 'type' => 'service', 'category_id' => $cats['Services']->id, 'unit_id' => $unit($u),
                'cost_price' => $cost, 'sale_price' => $price, 'alert_threshold' => 0, 'is_active' => true,
            ], null, $keeper);
        }

        $suppliers = [];
        foreach (['Grossiste Exemple SARL (fictif)', 'Dépôt Boissons Démo (fictif)'] as $n) {
            $suppliers[] = Supplier::query()->firstOrCreate(['name' => $n], ['phone' => '+243 000 000 001', 'contact_person' => 'Contact fictif', 'is_active' => true]);
        }
        $numbering = app(NumberingService::class);
        $customers = [];
        foreach ([['Alimentation Mama Demo (fictif)', '+243 000 100 001', '500000'], ['Restaurant Le Fictif', '+243 000 100 002', null], ['Jean Exemple', '+243 000 100 003', '150000']] as [$n, $ph, $limit]) {
            $customers[] = Customer::query()->firstOrCreate(['name' => $n], ['code' => $numbering->next('customer'), 'phone' => $ph, 'credit_limit' => $limit, 'is_active' => true]);
        }

        $cashSvc = app(CashService::class);
        $saleSvc = app(SaleService::class);
        $cash = PaymentMethod::query()->where('code', 'cash')->first();
        $mobile = PaymentMethod::query()->where('code', 'mobile_money')->first();
        mt_srand(2026);

        for ($d = 0; $d < 20; $d++) {
            $day = $start->copy()->addDays($d);
            Carbon::setTestNow($day->copy()->setTime(8, 0));
            $sellers = [$cashier, $cashier2];
            foreach ($sellers as $seller) {
                $session = $cashSvc->open($seller, '50000');
                $n = mt_rand(3, 7);
                for ($i = 0; $i < $n; $i++) {
                    Carbon::setTestNow($day->copy()->setTime(9 + $i, mt_rand(0, 59)));
                    $refs = array_rand(array_filter($products, fn ($p) => $p->is_active), mt_rand(1, 3));
                    $items = [];
                    foreach ((array) $refs as $ref) {
                        $p = $products[$ref]->fresh();
                        if ($p->type === 'product' && (float) $p->stock_quantity < 3) {
                            continue;
                        }
                        $items[] = ['product_id' => $p->id, 'quantity' => (string) mt_rand(1, 3)];
                    }
                    if (! $items) {
                        continue;
                    }
                    $byId = collect($products)->keyBy('id');
                    $total = Money::add(...array_map(fn ($it) => Money::mul($byId[$it['product_id']]->sale_price, $it['quantity']), $items));
                    $credit = $i === 0 && $d % 4 === 0;
                    $method = $i % 3 === 0 ? $mobile : $cash;
                    $paid = $credit ? Money::money(Money::of($total)->dividedBy(2, 0, RoundingMode::DOWN)) : $total;
                    try {
                        $saleSvc->create([
                            'client_uuid' => (string) Str::uuid(),
                            'customer_id' => $credit ? $customers[$d % 3]->id : (mt_rand(0, 4) === 0 ? $customers[mt_rand(0, 2)]->id : null),
                            'items' => $items,
                            'payments' => [['payment_method_id' => $method->id, 'amount' => $paid]],
                        ], $credit ? $manager : $seller, $session);
                    } catch (\Throwable) {
                        // vente de démonstration ignorée (ex. plafond de crédit)
                    }
                }
                Carbon::setTestNow($day->copy()->setTime(18, 30));
                $expected = $cashSvc->expectedCash($session);
                $gap = $d % 6 === 5 ? '-500' : '0';
                $cashSvc->close($session, Money::money(Money::of($expected)->plus($gap)), $gap !== '0' ? 'Écart constaté au comptage (démo)' : null, $seller);
            }

            if ($d % 5 === 2) {
                Carbon::setTestNow($day->copy()->setTime(7, 30));
                $ps2 = app(PurchaseService::class);
                $purchase = $ps2->saveDraft(null, ['supplier_id' => $suppliers[$d % 2]->id, 'supplier_reference' => 'BL-DEMO-'.$d, 'purchase_date' => $day->toDateString()], [
                    ['product_id' => $products['BOI-001']->id, 'quantity' => '96', 'unit_cost' => '950'],
                    ['product_id' => $products['ALI-003']->id, 'quantity' => '24', 'unit_cost' => '4300'],
                    ['product_id' => $products['HYG-001']->id, 'quantity' => '48', 'unit_cost' => '820'],
                ], $keeper);
                $ps2->validate($purchase, $manager);
            }
            if ($d % 7 === 3) {
                Carbon::setTestNow($day->copy()->setTime(12, 0));
                $mSession = $cashSvc->open($manager, '100000');
                app(ExpenseService::class)->create([
                    'expense_category_id' => ExpenseCategory::query()->where('name', 'Transport')->value('id'),
                    'expense_date' => $day->toDateString(), 'amount' => '15000', 'beneficiary' => 'Transporteur fictif',
                    'reason' => 'Transport de marchandises (démo)', 'payment_method_id' => $cash->id, 'paid_from_cash' => true,
                ], $manager);
                $cashSvc->close($mSession, $cashSvc->expectedCash($mSession), null, $manager);
            }
        }

        // Opérations du jour : caisse ouverte, retour, règlement, dépense en attente
        Carbon::setTestNow();
        $session = $cashSvc->open($cashier, '40000');
        $mSession = $cashSvc->open($manager, '80000');
        [$sale] = $saleSvc->create(['client_uuid' => (string) Str::uuid(), 'items' => [
            ['product_id' => $products['ALI-002']->id, 'quantity' => '2'], ['product_id' => $products['BOI-002']->id, 'quantity' => '3'],
        ], 'payments' => [['payment_method_id' => $cash->id, 'amount' => '50000']]], $cashier, $session);
        app(ReturnService::class)->create($sale, [['sale_item_id' => $sale->items()->where('product_reference', 'BOI-002')->value('id'), 'quantity' => '1', 'condition' => 'damaged']],
            'Bouteille abîmée (démo)', $manager, $manager);
        $debtor = $customers[0];
        if (Money::isPositive($debtor->balanceDue())) {
            app(SettlementService::class)->settle($debtor, Money::money(Money::min('10000', $debtor->balanceDue())), $mobile, $manager, (string) Str::uuid(), 'MM-DEMO-001');
        }
        app(ExpenseService::class)->create([
            'expense_category_id' => ExpenseCategory::query()->where('name', 'Électricité et eau')->value('id'),
            'expense_date' => now()->toDateString(), 'amount' => '25000', 'beneficiary' => 'Fournisseur d\'électricité (fictif)',
            'reason' => 'Facture du mois (démo)', 'payment_method_id' => $cash->id, 'paid_from_cash' => true,
        ], $cashier);

        $this->command?->info('Données de démonstration FICTIVES créées. Comptes : admin / admin12345 ; gerant, caissier, caissier2, magasinier / demo1234');
    }
}
