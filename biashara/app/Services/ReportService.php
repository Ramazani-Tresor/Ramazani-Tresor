<?php

namespace App\Services;

use App\Models\CashSession;
use App\Models\Expense;
use App\Models\InventoryItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturnItem;
use App\Models\StockMovement;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Rapports filtrables (période, utilisateur, produit, catégorie).
 * Chaque rapport renvoie une structure tabulaire commune, exploitée par l'écran, l'export Excel/CSV et le PDF.
 */
class ReportService
{
    public const REPORTS = [
        'sales_detailed' => ['Ventes détaillées', 'reports.view'],
        'sales_summary' => ['Ventes synthétiques (par jour)', 'reports.view'],
        'sales_by_product' => ['Ventes par article', 'reports.view'],
        'payments' => ['Encaissements par mode de paiement', 'reports.view'],
        'stock' => ['Stocks disponibles', 'reports.view'],
        'movements' => ['Mouvements de stock', 'reports.view'],
        'purchases' => ['Approvisionnements', 'reports.view'],
        'returns' => ['Retours et remboursements', 'reports.view'],
        'expenses' => ['Dépenses', 'reports.view'],
        'inventories' => ['Inventaires et écarts', 'reports.view'],
        'receivables' => ['Créances clients', 'reports.view'],
        'cash_closures' => ['Clôtures de caisse', 'reports.view'],
        'margins' => ['Marges commerciales par article', 'reports.financial'],
        'margins_summary' => ['Compte de résultat estimatif', 'reports.financial'],
    ];

    /**
     * @param  array{from:CarbonInterface, to:CarbonInterface, user_id?:?int, product_id?:?int, category_id?:?int}  $f
     * @return array{title:string, columns:array<string,string>, numeric:string[], rows:array, totals:array}
     */
    public function build(string $type, array $f): array
    {
        $method = 'report'.str_replace('_', '', ucwords($type, '_'));
        $data = $this->{$method}($f);
        $data['title'] = self::REPORTS[$type][0];

        return $data;
    }

    private function reportSalesDetailed(array $f): array
    {
        $rows = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('users', 'users.id', '=', 'sales.user_id')
            ->leftJoin('customers', 'customers.id', '=', 'sales.customer_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->whereBetween('sales.sold_at', [$f['from'], $f['to']])
            ->when($f['user_id'] ?? null, fn ($q, $v) => $q->where('sales.user_id', $v))
            ->when($f['product_id'] ?? null, fn ($q, $v) => $q->where('sale_items.product_id', $v))
            ->when($f['category_id'] ?? null, fn ($q, $v) => $q->where('products.category_id', $v))
            ->orderBy('sales.sold_at')
            ->select('sales.sold_at', 'sales.number', 'sales.status', 'users.name as seller', 'customers.name as customer',
                'sale_items.product_name', 'sale_items.quantity', 'sale_items.unit_price', 'sale_items.discount_amount',
                'sale_items.tax_amount', 'sale_items.line_total', 'sale_items.returned_quantity')
            ->get()
            ->map(fn ($r) => [
                'date' => $this->dt($r->sold_at), 'number' => $r->number, 'status' => $r->status === 'cancelled' ? 'Annulée' : 'Validée',
                'seller' => $r->seller, 'customer' => $r->customer ?? 'Client de passage', 'product' => $r->product_name,
                'quantity' => Money::qty($r->quantity), 'unit_price' => Money::money($r->unit_price),
                'discount' => Money::money($r->discount_amount), 'tax' => Money::money($r->tax_amount),
                'total' => Money::money($r->line_total), 'returned' => Money::qty($r->returned_quantity),
            ])->all();

        return $this->table([
            'date' => 'Date', 'number' => 'N° vente', 'status' => 'Statut', 'seller' => 'Vendeur', 'customer' => 'Client',
            'product' => 'Article', 'quantity' => 'Qté', 'unit_price' => 'P.U.', 'discount' => 'Remise', 'tax' => 'Taxe',
            'total' => 'Total TTC', 'returned' => 'Qté retournée',
        ], ['quantity', 'unit_price', 'discount', 'tax', 'total', 'returned'], $rows, ['quantity', 'discount', 'tax', 'total', 'returned']);
    }

    private function reportSalesSummary(array $f): array
    {
        $rows = Sale::query()
            ->whereBetween('sold_at', [$f['from'], $f['to']])
            ->when($f['user_id'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->selectRaw('DATE(sold_at) as day, COUNT(*) as count, SUM(subtotal) as subtotal, SUM(discount_total) as discount, SUM(tax_total) as tax, SUM(total) as total, SUM(amount_paid) as paid, SUM(amount_due) as due, SUM(returned_total) as returned')
            ->groupBy(DB::raw('DATE(sold_at)'))->orderBy('day')->get()
            ->map(fn ($r) => [
                'day' => Carbon::parse($r->day)->format('d/m/Y'), 'count' => (string) $r->count,
                'subtotal' => Money::money($r->subtotal), 'discount' => Money::money($r->discount), 'tax' => Money::money($r->tax),
                'total' => Money::money($r->total), 'returned' => Money::money($r->returned), 'paid' => Money::money($r->paid), 'due' => Money::money($r->due),
            ])->all();
        $num = ['count', 'subtotal', 'discount', 'tax', 'total', 'returned', 'paid', 'due'];

        return $this->table(['day' => 'Jour', 'count' => 'Nb ventes', 'subtotal' => 'Brut HT', 'discount' => 'Remises', 'tax' => 'Taxes',
            'total' => 'Total TTC', 'returned' => 'Retours', 'paid' => 'Payé', 'due' => 'Reste dû'], $num, $rows, $num);
    }

    private function reportSalesByProduct(array $f): array
    {
        $rows = $this->itemsQuery($f)
            ->groupBy('sale_items.product_id', 'sale_items.product_reference', 'sale_items.product_name', 'categories.name')
            ->selectRaw('sale_items.product_reference as ref, sale_items.product_name as name, categories.name as category, SUM(sale_items.quantity) as qty, SUM(sale_items.returned_quantity) as returned, SUM(sale_items.line_total) as total')
            ->orderByDesc('qty')->get()
            ->map(fn ($r) => ['ref' => $r->ref, 'name' => $r->name, 'category' => $r->category, 'qty' => Money::qty($r->qty), 'returned' => Money::qty($r->returned), 'total' => Money::money($r->total)])->all();

        return $this->table(['ref' => 'Référence', 'name' => 'Article', 'category' => 'Catégorie', 'qty' => 'Qté vendue', 'returned' => 'Qté retournée', 'total' => 'Total TTC'],
            ['qty', 'returned', 'total'], $rows, ['qty', 'returned', 'total']);
    }

    private function reportPayments(array $f): array
    {
        $rows = Payment::query()
            ->join('payment_methods', 'payment_methods.id', '=', 'payments.payment_method_id')
            ->whereBetween('payments.paid_at', [$f['from'], $f['to']])
            ->when($f['user_id'] ?? null, fn ($q, $v) => $q->where('payments.user_id', $v))
            ->groupBy('payment_methods.name', 'payments.kind', 'payments.currency')
            ->selectRaw('payment_methods.name as method, payments.kind as kind, payments.currency as currency, COUNT(*) as count, SUM(payments.amount) as amount, SUM(payments.currency_amount) as currency_amount')
            ->orderBy('method')->get()
            ->map(fn ($r) => ['method' => $r->method, 'kind' => Payment::KINDS[$r->kind] ?? $r->kind, 'currency' => $r->currency, 'count' => (string) $r->count,
                'currency_amount' => Money::money($r->currency_amount), 'amount' => Money::money($r->amount)])->all();

        return $this->table(['method' => 'Mode', 'kind' => 'Nature', 'currency' => 'Devise reçue', 'count' => 'Nb', 'currency_amount' => 'Montant (devise reçue)', 'amount' => 'Montant (devise principale)'],
            ['count', 'currency_amount', 'amount'], $rows, ['count', 'amount']);
    }

    private function reportStock(array $f): array
    {
        $rows = Product::query()->with(['category', 'unit'])->where('type', 'product')
            ->when($f['product_id'] ?? null, fn ($q, $v) => $q->where('id', $v))
            ->when($f['category_id'] ?? null, fn ($q, $v) => $q->where('category_id', $v))
            ->orderBy('name')->get()
            ->map(fn (Product $p) => [
                'ref' => $p->reference, 'name' => $p->name, 'category' => $p->category->name, 'unit' => $p->unit->symbol,
                'stock' => Money::qty($p->stock_quantity), 'damaged' => Money::qty($p->damaged_quantity), 'threshold' => Money::qty($p->alert_threshold),
                'status' => Money::cmp($p->stock_quantity, 0) <= 0 ? 'Rupture' : ($p->isLowStock() ? 'Sous le seuil' : 'OK'),
                'avg_cost' => Money::money($p->average_cost), 'value' => Money::money(Money::mul($p->stock_quantity, $p->average_cost)),
            ])->all();

        return $this->table(['ref' => 'Référence', 'name' => 'Article', 'category' => 'Catégorie', 'unit' => 'Unité', 'stock' => 'Stock vendable',
            'damaged' => 'Endommagé', 'threshold' => 'Seuil', 'status' => 'État', 'avg_cost' => 'CMP', 'value' => 'Valeur au CMP'],
            ['stock', 'damaged', 'threshold', 'avg_cost', 'value'], $rows, ['value']);
    }

    private function reportMovements(array $f): array
    {
        $rows = StockMovement::query()->with(['product', 'user'])
            ->whereBetween('occurred_at', [$f['from'], $f['to']])
            ->when($f['user_id'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($f['product_id'] ?? null, fn ($q, $v) => $q->where('product_id', $v))
            ->when($f['category_id'] ?? null, fn ($q, $v) => $q->whereHas('product', fn ($p) => $p->where('category_id', $v)))
            ->orderBy('occurred_at')->orderBy('id')->get()
            ->map(fn (StockMovement $m) => [
                'date' => $this->dt($m->occurred_at), 'product' => $m->product->name, 'type' => $m->typeLabel(),
                'bucket' => $m->bucket === 'damaged' ? 'Endommagé' : 'Vendable', 'quantity' => Money::qty($m->quantity),
                'balance' => Money::qty($m->balance_after), 'cost' => Money::money($m->unit_cost), 'document' => $m->source_number,
                'reason' => $m->reason, 'user' => $m->user->name,
            ])->all();

        return $this->table(['date' => 'Date', 'product' => 'Article', 'type' => 'Type', 'bucket' => 'Compartiment', 'quantity' => 'Quantité',
            'balance' => 'Solde après', 'cost' => 'Coût unitaire', 'document' => 'Document', 'reason' => 'Motif', 'user' => 'Utilisateur'],
            ['quantity', 'balance', 'cost'], $rows, []);
    }

    private function reportPurchases(array $f): array
    {
        $rows = Purchase::query()->with(['supplier', 'user'])->where('status', 'validated')
            ->whereBetween('purchase_date', [$f['from']->toDateString(), $f['to']->toDateString().' 23:59:59'])
            ->when($f['user_id'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($f['product_id'] ?? null, fn ($q, $v) => $q->whereHas('items', fn ($i) => $i->where('product_id', $v)))
            ->orderBy('purchase_date')->get()
            ->map(fn (Purchase $p) => ['date' => $p->purchase_date->format('d/m/Y'), 'number' => $p->number, 'supplier' => $p->supplier->name,
                'reference' => $p->supplier_reference, 'paid_cash' => $p->paid_from_cash ? 'Oui' : 'Non', 'user' => $p->user->name, 'total' => Money::money($p->total)])->all();

        return $this->table(['date' => 'Date', 'number' => 'N° bon', 'supplier' => 'Fournisseur', 'reference' => 'Réf. fournisseur', 'paid_cash' => 'Payé en caisse', 'user' => 'Saisi par', 'total' => 'Total'],
            ['total'], $rows, ['total']);
    }

    private function reportReturns(array $f): array
    {
        $rows = SaleReturnItem::query()
            ->join('sale_returns', 'sale_returns.id', '=', 'sale_return_items.sale_return_id')
            ->join('sales', 'sales.id', '=', 'sale_returns.sale_id')
            ->join('sale_items', 'sale_items.id', '=', 'sale_return_items.sale_item_id')
            ->join('users as u', 'u.id', '=', 'sale_returns.user_id')
            ->join('users as a', 'a.id', '=', 'sale_returns.authorized_by')
            ->whereBetween('sale_returns.returned_at', [$f['from'], $f['to']])
            ->when($f['product_id'] ?? null, fn ($q, $v) => $q->where('sale_return_items.product_id', $v))
            ->orderBy('sale_returns.returned_at')
            ->select('sale_returns.returned_at', 'sale_returns.number', 'sales.number as sale_number', 'sale_returns.is_cancellation', 'sale_items.product_name',
                'sale_return_items.quantity', 'sale_return_items.condition', 'sale_return_items.line_value', 'sale_returns.refund_amount', 'sale_returns.reason', 'u.name as user', 'a.name as authorizer')
            ->get()
            ->map(fn ($r) => ['date' => $this->dt($r->returned_at), 'number' => $r->number, 'sale' => $r->sale_number,
                'nature' => $r->is_cancellation ? 'Annulation' : 'Retour', 'product' => $r->product_name, 'quantity' => Money::qty($r->quantity),
                'condition' => $r->condition === 'damaged' ? 'Endommagé' : 'Revendable', 'value' => Money::money($r->line_value),
                'refund' => Money::money($r->refund_amount), 'reason' => $r->reason, 'user' => $r->user, 'authorizer' => $r->authorizer])->all();

        return $this->table(['date' => 'Date', 'number' => 'N° retour', 'sale' => 'Vente', 'nature' => 'Nature', 'product' => 'Article', 'quantity' => 'Qté',
            'condition' => 'État', 'value' => 'Valeur', 'refund' => 'Remboursé (doc.)', 'reason' => 'Motif', 'user' => 'Saisi par', 'authorizer' => 'Autorisé par'],
            ['quantity', 'value', 'refund'], $rows, ['quantity', 'value']);
    }

    private function reportExpenses(array $f): array
    {
        $rows = Expense::query()->with(['category', 'method', 'user'])
            ->whereBetween('expense_date', [$f['from']->toDateString(), $f['to']->toDateString().' 23:59:59'])
            ->when($f['user_id'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->orderBy('expense_date')->get()
            ->map(fn (Expense $e) => ['date' => $e->expense_date->format('d/m/Y'), 'number' => $e->number, 'category' => $e->category->name,
                'beneficiary' => $e->beneficiary, 'reason' => $e->reason, 'method' => $e->method->name, 'cash' => $e->paid_from_cash ? 'Oui' : 'Non',
                'status' => Expense::STATUSES[$e->status], 'user' => $e->user->name, 'amount' => Money::money($e->amount)])->all();
        $approved = collect($rows)->where('status', 'Validée')->pluck('amount')->all();
        $data = $this->table(['date' => 'Date', 'number' => 'N°', 'category' => 'Catégorie', 'beneficiary' => 'Bénéficiaire', 'reason' => 'Motif', 'method' => 'Mode',
            'cash' => 'Payée en caisse', 'status' => 'Statut', 'user' => 'Saisie par', 'amount' => 'Montant'], ['amount'], $rows, []);
        $data['totals'] = ['amount' => Money::money(Money::add(...$approved))];
        $data['note'] = 'Le total ne reprend que les dépenses validées.';

        return $data;
    }

    private function reportInventories(array $f): array
    {
        $rows = InventoryItem::query()->with(['product'])
            ->join('inventories', 'inventories.id', '=', 'inventory_items.inventory_id')
            ->whereIn('inventories.status', ['open', 'validated'])
            ->whereBetween('inventories.opened_at', [$f['from'], $f['to']])
            ->whereNotNull('inventory_items.counted_quantity')
            ->when($f['product_id'] ?? null, fn ($q, $v) => $q->where('inventory_items.product_id', $v))
            ->select('inventory_items.*', 'inventories.number as inv_number', 'inventories.status as inv_status')
            ->orderBy('inventories.id')->get()
            ->map(fn ($i) => ['inventory' => $i->inv_number, 'status' => $i->inv_status === 'validated' ? 'Validé' : 'Ouvert', 'product' => $i->product->name,
                'theoretical' => Money::qty($i->theoretical_quantity), 'counted' => Money::qty($i->counted_quantity), 'difference' => Money::qty($i->difference),
                'value' => Money::money(Money::mul($i->difference, $i->unit_cost)), 'justification' => $i->justification])->all();

        return $this->table(['inventory' => 'Inventaire', 'status' => 'Statut', 'product' => 'Article', 'theoretical' => 'Théorique', 'counted' => 'Compté',
            'difference' => 'Écart', 'value' => 'Valeur écart', 'justification' => 'Justification'], ['theoretical', 'counted', 'difference', 'value'], $rows, ['value']);
    }

    private function reportReceivables(array $f): array
    {
        $rows = Sale::query()->with('customer')->where('status', 'validated')->where('amount_due', '>', 0)
            ->orderBy('sold_at')->get()
            ->map(fn (Sale $s) => ['customer' => $s->customer?->name, 'phone' => $s->customer?->phone, 'number' => $s->number, 'date' => $this->dt($s->sold_at),
                'age' => (string) (int) $s->sold_at->diffInDays(now()), 'total' => Money::money($s->total), 'paid' => Money::money($s->amount_paid), 'due' => Money::money($s->amount_due)])->all();

        return $this->table(['customer' => 'Client', 'phone' => 'Téléphone', 'number' => 'Vente', 'date' => 'Date', 'age' => 'Ancienneté (j)', 'total' => 'Total', 'paid' => 'Payé', 'due' => 'Reste dû'],
            ['age', 'total', 'paid', 'due'], $rows, ['total', 'paid', 'due']);
    }

    private function reportCashClosures(array $f): array
    {
        $rows = CashSession::query()->with(['user', 'closer'])->where('status', 'closed')
            ->whereBetween('closed_at', [$f['from'], $f['to']])
            ->when($f['user_id'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->orderBy('closed_at')->get()
            ->map(fn (CashSession $s) => ['number' => $s->number, 'cashier' => $s->user->name, 'opened' => $this->dt($s->opened_at), 'closed' => $this->dt($s->closed_at),
                'opening' => Money::money($s->opening_amount), 'expected' => Money::money($s->expected_cash), 'counted' => Money::money($s->counted_cash),
                'difference' => Money::money($s->difference), 'electronic' => Money::money($s->electronic_total), 'notes' => $s->closing_notes])->all();

        return $this->table(['number' => 'Session', 'cashier' => 'Caissier', 'opened' => 'Ouverture', 'closed' => 'Clôture', 'opening' => 'Fonds initial',
            'expected' => 'Espèces théoriques', 'counted' => 'Espèces comptées', 'difference' => 'Écart', 'electronic' => 'Électronique', 'notes' => 'Justification'],
            ['opening', 'expected', 'counted', 'difference', 'electronic'], $rows, ['difference', 'electronic']);
    }

    private function reportMargins(array $f): array
    {
        $sold = $this->itemsQuery($f)
            ->groupBy('sale_items.product_id', 'sale_items.product_name')
            ->selectRaw('sale_items.product_id as pid, sale_items.product_name as name, SUM(sale_items.quantity) as qty, SUM(sale_items.line_total - sale_items.tax_amount) as ht, SUM(sale_items.quantity * sale_items.unit_cost) as cost')
            ->get()->keyBy('pid');
        $ret = SaleReturnItem::query()
            ->join('sale_returns', 'sale_returns.id', '=', 'sale_return_items.sale_return_id')
            ->join('sale_items', 'sale_items.id', '=', 'sale_return_items.sale_item_id')
            ->whereBetween('sale_returns.returned_at', [$f['from'], $f['to']])
            ->groupBy('sale_return_items.product_id')
            ->selectRaw("sale_return_items.product_id as pid, SUM(sale_return_items.quantity) as qty,
                SUM(CASE WHEN sale_items.line_total = 0 THEN 0 ELSE sale_return_items.line_value * (sale_items.line_total - sale_items.tax_amount) / sale_items.line_total END) as ht,
                SUM(CASE WHEN sale_return_items.condition = 'resaleable' THEN sale_return_items.quantity * sale_return_items.unit_cost ELSE 0 END) as cost")
            ->get()->keyBy('pid');

        $rows = [];
        foreach ($sold as $pid => $s) {
            $r = $ret->get($pid);
            $qty = Money::of($s->qty)->minus(Money::of($r->qty ?? 0));
            $ht = Money::of($s->ht)->minus(Money::of($r->ht ?? 0));
            $cost = Money::of($s->cost)->minus(Money::of($r->cost ?? 0));
            $margin = $ht->minus($cost);
            $rows[] = ['name' => $s->name, 'qty' => Money::qty($qty), 'ht' => Money::money($ht), 'cost' => Money::money($cost), 'margin' => Money::money($margin),
                'rate' => $ht->isZero() ? '0' : (string) Money::div($margin->multipliedBy(100), $ht, 1)];
        }
        usort($rows, fn ($a, $b) => Money::cmp($b['margin'], $a['margin']));
        $data = $this->table(['name' => 'Article', 'qty' => 'Qté nette', 'ht' => 'Ventes nettes HT', 'cost' => 'Coût des ventes (CMP)', 'margin' => 'Marge commerciale', 'rate' => 'Taux (%)'],
            ['qty', 'ht', 'cost', 'margin', 'rate'], $rows, ['ht', 'cost', 'margin']);
        $data['note'] = 'Valorisation au coût moyen pondéré mobile, figé au moment de chaque vente. Les retours de la période sont déduits.';

        return $data;
    }

    private function reportMarginsSummary(array $f): array
    {
        $m = app(FinancialMetrics::class)->compute($f['from'], $f['to'], $f['user_id'] ?? null);
        $lines = [
            ['Chiffre d\'affaires TTC (ventes − retours)', $m['revenue_ttc']],
            ['− Taxes collectées', $m['taxes']],
            ['= Ventes nettes HT', $m['net_sales_ht']],
            ['− Coût des marchandises vendues (CMP)', $m['cogs']],
            ['= Marge commerciale', $m['margin']],
            ['− Dépenses d\'exploitation validées', $m['expenses']],
            ['− Pertes de stock nettes (casses, écarts d\'inventaire)', $m['stock_losses']],
            ['= Résultat estimatif', $m['estimated_result']],
            ['Pour information : encaissements nets', $m['net_collected']],
            ['Pour information : créances clients en cours', $m['receivables']],
        ];
        $rows = array_map(fn ($l) => ['label' => $l[0], 'amount' => $l[1]], $lines);
        $data = $this->table(['label' => 'Rubrique', 'amount' => 'Montant'], ['amount'], $rows, []);
        $data['note'] = 'Résultat indicatif de gestion, non comptable. Les approvisionnements ne sont pas des dépenses : ils sont intégrés au coût des marchandises vendues au fil des ventes.';

        return $data;
    }

    private function itemsQuery(array $f)
    {
        return SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->whereBetween('sales.sold_at', [$f['from'], $f['to']])
            ->when($f['user_id'] ?? null, fn ($q, $v) => $q->where('sales.user_id', $v))
            ->when($f['product_id'] ?? null, fn ($q, $v) => $q->where('sale_items.product_id', $v))
            ->when($f['category_id'] ?? null, fn ($q, $v) => $q->where('products.category_id', $v));
    }

    private function table(array $columns, array $numeric, array $rows, array $sumKeys): array
    {
        $totals = [];
        foreach ($sumKeys as $k) {
            $totals[$k] = (string) Money::add(...array_column($rows, $k));
        }

        return ['columns' => $columns, 'numeric' => $numeric, 'rows' => $rows, 'totals' => $totals, 'note' => null];
    }

    private function dt($value): string
    {
        return $value ? Carbon::parse($value)->format('d/m/Y H:i') : '';
    }
}
