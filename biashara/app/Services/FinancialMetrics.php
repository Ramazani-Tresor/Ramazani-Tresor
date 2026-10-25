<?php

namespace App\Services;

use App\Models\CashSession;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturnItem;
use App\Models\StockMovement;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Définitions des indicateurs (toutes en devise principale, sur une période [début ; fin]) :
 *
 *  - Chiffre d'affaires TTC     = Σ totaux des ventes de la période − Σ valeurs des retours/annulations de la période.
 *  - Ventes nettes HT           = Σ (ligne TTC − taxe) des ventes − part HT des retours.
 *  - Taxes collectées           = Chiffre d'affaires TTC − Ventes nettes HT.
 *  - Coût des marchandises vendues (CMV) = Σ qté vendue × coût de sortie (CMP figé à la vente)
 *                                 − Σ qté retournée revendable × même coût.
 *  - Marge commerciale          = Ventes nettes HT − CMV.  (Les dépenses n'entrent PAS dans la marge.)
 *  - Dépenses d'exploitation    = Σ dépenses validées (date de dépense dans la période). Les approvisionnements
 *                                 n'en font pas partie : ils sont stockés puis passent dans le CMV à la vente.
 *  - Pertes de stock nettes     = valeur des pertes/casses/mises au rebut + écarts d'inventaire (négatifs − positifs).
 *  - Résultat estimatif         = Marge commerciale − Dépenses d'exploitation − Pertes de stock nettes.
 *  - Encaissements              = Σ paiements reçus (ventes + règlements de créances) − remboursements.
 *  - Créances clients           = Σ restes dus des ventes validées (à l'instant présent).
 *  - Solde de caisse            = Σ espèces théoriques des sessions ouvertes.
 */
class FinancialMetrics
{
    public function compute(CarbonInterface $from, CarbonInterface $to, ?int $userId = null): array
    {
        $sales = Sale::query()->whereBetween('sold_at', [$from, $to])->when($userId, fn ($q) => $q->where('user_id', $userId));
        $salesTtc = Money::of((clone $sales)->sum('total'));
        $salesCount = (clone $sales)->where('status', 'validated')->count();

        $itemsQ = SaleItem::query()->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->whereBetween('sales.sold_at', [$from, $to])->when($userId, fn ($q) => $q->where('sales.user_id', $userId));
        $salesHt = Money::of((clone $itemsQ)->sum(DB::raw('sale_items.line_total - sale_items.tax_amount')));
        $cogsGross = Money::of((clone $itemsQ)->sum(DB::raw('sale_items.quantity * sale_items.unit_cost')));

        $retItemsQ = SaleReturnItem::query()
            ->join('sale_returns', 'sale_returns.id', '=', 'sale_return_items.sale_return_id')
            ->join('sale_items', 'sale_items.id', '=', 'sale_return_items.sale_item_id')
            ->join('sales', 'sales.id', '=', 'sale_returns.sale_id')
            ->whereBetween('sale_returns.returned_at', [$from, $to])->when($userId, fn ($q) => $q->where('sales.user_id', $userId));
        $returnsTtc = Money::of((clone $retItemsQ)->sum('sale_return_items.line_value'));
        $returnsHt = Money::of((clone $retItemsQ)->sum(DB::raw(
            'CASE WHEN sale_items.line_total = 0 THEN 0 ELSE sale_return_items.line_value * (sale_items.line_total - sale_items.tax_amount) / sale_items.line_total END'
        )));
        $cogsReturned = Money::of((clone $retItemsQ)->where('sale_return_items.condition', 'resaleable')
            ->sum(DB::raw('sale_return_items.quantity * sale_return_items.unit_cost')));

        $revenueTtc = $salesTtc->minus($returnsTtc);
        $netSalesHt = $salesHt->minus($returnsHt);
        $cogs = $cogsGross->minus($cogsReturned);
        $margin = $netSalesHt->minus($cogs);

        $expenses = Money::of(Expense::query()->where('status', 'approved')
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString().' 23:59:59'])->sum('amount'));

        $losses = Money::of(StockMovement::query()->whereBetween('occurred_at', [$from, $to])
            ->whereIn('type', ['loss', 'inventory_adjustment'])
            ->where('bucket', 'available')
            ->sum(DB::raw('-1 * quantity * unit_cost')));
        // Retours endommagés : leur coût reste dans le CMV (seuls les retours revendables en sont déduits),
        // la mise au rebut du stock endommagé n'est donc pas recomptée ici. Les retours fournisseurs
        // ne sont pas des pertes (marchandise rendue au fournisseur).

        $payQ = Payment::query()->whereBetween('paid_at', [$from, $to])->when($userId, fn ($q) => $q->where('user_id', $userId));
        $collected = Money::of((clone $payQ)->whereIn('kind', ['sale', 'debt_settlement'])->sum('amount'));
        $refunds = Money::of((clone $payQ)->where('kind', 'refund')->sum('amount'))->abs();

        $openCash = Money::of(0);
        foreach (CashSession::query()->where('status', 'open')->get() as $s) {
            $openCash = $openCash->plus(Money::of(app(CashService::class)->expectedCash($s)));
        }

        return [
            'sales_count' => $salesCount,
            'sales_ttc' => Money::money($salesTtc),
            'returns_ttc' => Money::money($returnsTtc),
            'revenue_ttc' => Money::money($revenueTtc),
            'net_sales_ht' => Money::money($netSalesHt),
            'taxes' => Money::money($revenueTtc->minus($netSalesHt)),
            'cogs' => Money::money($cogs),
            'margin' => Money::money($margin),
            'margin_rate' => $netSalesHt->isZero() ? '0' : (string) Money::div($margin->multipliedBy(100), $netSalesHt, 1),
            'expenses' => Money::money($expenses),
            'stock_losses' => Money::money($losses),
            'estimated_result' => Money::money($margin->minus($expenses)->minus($losses)),
            'collected' => Money::money($collected),
            'refunds' => Money::money($refunds),
            'net_collected' => Money::money($collected->minus($refunds)),
            'receivables' => Money::money(Sale::query()->where('status', 'validated')->sum('amount_due')),
            'cash_balance' => Money::money($openCash),
            'average_ticket' => $salesCount ? Money::money(Money::div($salesTtc, $salesCount, 2)) : '0.00',
        ];
    }

    public static function definitions(): array
    {
        return [
            'revenue_ttc' => "Chiffre d'affaires TTC : total des ventes de la période, diminué des retours et annulations.",
            'net_sales_ht' => 'Ventes nettes HT : chiffre d\'affaires hors taxes collectées.',
            'cogs' => 'Coût des marchandises vendues : quantités vendues × coût moyen pondéré au moment de la vente, moins les retours remis en stock.',
            'margin' => 'Marge commerciale : ventes nettes HT − coût des marchandises vendues. Les dépenses n\'y sont pas déduites.',
            'expenses' => "Dépenses d'exploitation validées (loyer, salaires…). Les approvisionnements n'en font pas partie.",
            'stock_losses' => 'Pertes de stock nettes : casses, pertes et écarts d\'inventaire valorisés au coût.',
            'estimated_result' => 'Résultat estimatif : marge commerciale − dépenses − pertes de stock. Indicatif, non comptable.',
            'net_collected' => 'Encaissements : sommes effectivement reçues (ventes et règlements de créances), moins les remboursements.',
            'receivables' => 'Créances clients : reste dû total des ventes à crédit, à cet instant.',
            'cash_balance' => 'Solde de caisse : espèces théoriquement présentes dans les caisses ouvertes.',
        ];
    }
}
