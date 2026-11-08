<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SyncOperation;
use App\Services\CashService;
use App\Services\FinancialMetrics;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /** Accueil : tuiles d'accès rapide selon les droits. */
    public function home(Request $request)
    {
        $user = $request->user();
        $session = $user->hasPermission('cash.use') ? $user->openCashSession() : null;

        return view('home', [
            'session' => $session,
            'sessionCash' => $session ? app(CashService::class)->expectedCash($session) : null,
            'lowStockCount' => $user->hasPermission('stock.view') ? Product::query()->active()->lowStock()->count() : null,
            'conflicts' => $user->hasPermission('sync.resolve') ? SyncOperation::query()->where('status', 'conflict')->count() : 0,
            'pendingExpenses' => $user->hasPermission('expenses.approve') ? Expense::query()->where('status', 'pending')->count() : 0,
        ]);
    }

    public function index(Request $request, FinancialMetrics $metrics)
    {
        [$from, $to] = $this->period($request, 'today');
        $user = $request->user();
        $restrictToSelf = ! $user->hasPermission('sales.view');
        $userId = $restrictToSelf ? $user->id : null;

        $today = $metrics->compute(now()->startOfDay(), now()->endOfDay(), $userId);
        $period = $metrics->compute($from, $to, $userId);

        // Évolution sur 30 jours (ou la période si plus longue)
        $chartFrom = $from->copy()->min(now()->subDays(29)->startOfDay());
        $salesByDay = Sale::query()->whereBetween('sold_at', [$chartFrom, $to])
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->selectRaw('DATE(sold_at) as d, SUM(total - returned_total) as t')->groupBy(DB::raw('DATE(sold_at)'))->pluck('t', 'd');
        $expensesByDay = Expense::query()->where('status', 'approved')->whereBetween('expense_date', [$chartFrom->toDateString(), $to->toDateString().' 23:59:59'])
            ->selectRaw('expense_date as d, SUM(amount) as t')->groupBy('expense_date')->get()
            ->mapWithKeys(fn ($r) => [substr((string) $r->d, 0, 10) => $r->t]);
        $labels = $salesSeries = $expenseSeries = [];
        for ($d = $chartFrom->copy(); $d->lte($to); $d->addDay()) {
            $key = $d->toDateString();
            $labels[] = $d->format('d/m');
            $salesSeries[] = (float) Money::money($salesByDay[$key] ?? 0);
            $expenseSeries[] = (float) Money::money($expensesByDay[$key] ?? 0);
        }

        $topProducts = SaleItem::query()->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->whereBetween('sales.sold_at', [$from, $to])->where('sales.status', 'validated')
            ->when($userId, fn ($q) => $q->where('sales.user_id', $userId))
            ->groupBy('sale_items.product_id', 'sale_items.product_name')
            ->selectRaw('sale_items.product_name as name, SUM(sale_items.quantity - sale_items.returned_quantity) as qty, SUM(sale_items.line_total) as total')
            ->orderByDesc('qty')->limit(8)->get();

        return view('dashboard', [
            'from' => $from, 'to' => $to, 'today' => $today, 'period' => $period,
            'restricted' => $restrictToSelf,
            'showFinancial' => $user->hasPermission('dashboard.financial'),
            'definitions' => FinancialMetrics::definitions(),
            'chart' => ['labels' => $labels, 'sales' => $salesSeries, 'expenses' => $expenseSeries],
            'topProducts' => $topProducts,
            'lowStock' => $user->hasPermission('stock.view') ? Product::query()->active()->lowStock()->with('unit')->orderBy('stock_quantity')->limit(10)->get() : collect(),
            'outOfStock' => Product::query()->active()->where('type', 'product')->where('stock_quantity', '<=', 0)->count(),
            'latestSales' => Sale::query()->with(['customer', 'user'])->when($userId, fn ($q) => $q->where('user_id', $userId))->latest('sold_at')->limit(8)->get(),
        ]);
    }
}
