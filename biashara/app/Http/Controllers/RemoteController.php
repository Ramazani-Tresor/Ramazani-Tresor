<?php

namespace App\Http\Controllers;

use App\Models\CashSession;
use App\Models\Device;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SyncOperation;
use App\Services\CashService;
use App\Services\FinancialMetrics;
use Illuminate\Http\Request;

/**
 * Consultation à distance (via HTTPS) pour le responsable : vue condensée, lisible sur smartphone,
 * avec l'heure de génération et la dernière synchronisation de chaque appareil.
 */
class RemoteController extends Controller
{
    public function index(Request $request, FinancialMetrics $metrics, CashService $cash)
    {
        [$from, $to] = $this->period($request, 'today');
        $sessions = CashSession::query()->with('user')->where('status', 'open')->get()
            ->map(fn ($s) => ['session' => $s, 'expected' => $cash->expectedCash($s), 'electronic' => $cash->electronicTotal($s)]);

        return view('remote', [
            'from' => $from, 'to' => $to,
            'metrics' => $metrics->compute($from, $to),
            'sessions' => $sessions,
            'devices' => Device::query()->with('user')->orderByDesc('last_seen_at')->limit(20)->get(),
            'pendingConflicts' => SyncOperation::query()->where('status', 'conflict')->count(),
            'lastSync' => Device::query()->max('last_sync_at'),
            'latestSales' => Sale::query()->with('user')->latest('sold_at')->limit(10)->get(),
            'lowStock' => Product::query()->active()->lowStock()->orderBy('stock_quantity')->limit(10)->get(),
            'latestExpenses' => Expense::query()->with('category')->latest('id')->limit(5)->get(),
            'generatedAt' => now(),
        ]);
    }
}
