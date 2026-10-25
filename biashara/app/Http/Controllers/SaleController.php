<?php

namespace App\Http\Controllers;

use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\User;
use App\Services\AuditService;
use App\Services\SaleService;
use App\Support\Settings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAny(['sales.view', 'sales.view_own']);
        [$from, $to] = $this->period($request, 'today');
        $all = $request->user()->hasPermission('sales.view');

        $query = Sale::query()->with(['customer', 'user'])
            ->whereBetween('sold_at', [$from, $to])
            ->when(! $all, fn ($q) => $q->where('user_id', $request->user()->id))
            ->when($all && $request->user_id, fn ($q) => $q->where('user_id', $request->user_id))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->credit, fn ($q) => $q->where('amount_due', '>', 0))
            ->when($request->q, fn ($q, $t) => $q->where(fn ($w) => $w->where('number', 'like', "%{$t}%")
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$t}%"))));

        return view('sales.index', [
            'sales' => (clone $query)->latest('sold_at')->paginate(25)->withQueryString(),
            'totals' => ['count' => (clone $query)->count(), 'total' => (clone $query)->sum('total'), 'due' => (clone $query)->sum('amount_due')],
            'from' => $from, 'to' => $to,
            'users' => $all ? User::query()->orderBy('name')->get(['id', 'name']) : collect(),
        ]);
    }

    public function show(Request $request, Sale $sale)
    {
        $this->authorizeSale($request, $sale);

        return view('sales.show', [
            'sale' => $sale->load(['items', 'payments.method', 'payments.user', 'customer', 'user', 'returns.items.saleItem', 'returns.authorizer', 'canceller']),
            'paymentMethods' => PaymentMethod::query()->active()->get(),
        ]);
    }

    /**
     * Reçu imprimable : ?format=a4|80|58. La première impression est l'original ;
     * toute impression suivante porte la mention « DUPLICATA » (et exige la permission de réimpression).
     */
    public function receipt(Request $request, Sale $sale)
    {
        $this->authorizeSale($request, $sale);
        $duplicate = $this->registerPrint($request, $sale);
        $format = in_array($request->format, ['a4', '80', '58'], true) ? $request->format : Settings::get('receipt_format', '80');

        return view('receipts.sale', $this->receiptData($sale, $duplicate) + ['format' => $format, 'autoPrint' => $request->boolean('print')]);
    }

    public function pdf(Request $request, Sale $sale)
    {
        $this->authorizeSale($request, $sale);
        $duplicate = $this->registerPrint($request, $sale);
        $format = in_array($request->format, ['a4', '80', '58'], true) ? $request->format : 'a4';
        $pdf = Pdf::loadView('receipts.sale', $this->receiptData($sale, $duplicate) + ['format' => $format, 'autoPrint' => false, 'pdf' => true]);
        if ($format !== 'a4') {
            $width = $format === '58' ? 164 : 227; // points (58 / 80 mm)
            $pdf->setPaper([0, 0, $width, 800 + 40 * $sale->items()->count()]);
        }

        return $pdf->download($sale->number.'.pdf');
    }

    public function cancel(Request $request, Sale $sale, SaleService $service)
    {
        $data = $request->validate([
            'reason' => 'required|string|max:255',
            'refund_method_id' => 'nullable|exists:payment_methods,id',
        ]);
        $service->cancel($sale, $data['reason'], $request->user(), isset($data['refund_method_id']) ? PaymentMethod::find($data['refund_method_id']) : null);

        return redirect()->route('sales.show', $sale)->with('success', 'Vente annulée : stock réintégré et remboursement enregistré.');
    }

    private function receiptData(Sale $sale, bool $duplicate): array
    {
        return [
            'sale' => $sale->load(['items', 'payments.method', 'customer', 'user']),
            'duplicate' => $duplicate,
            'shop' => Settings::all(),
        ];
    }

    private function registerPrint(Request $request, Sale $sale): bool
    {
        $duplicate = $sale->print_count > 0;
        if ($duplicate && ! $request->user()->hasPermission('sales.reprint') && $sale->user_id !== $request->user()->id) {
            abort(403);
        }
        $sale->increment('print_count');
        if ($duplicate) {
            AuditService::log('sale.reprint', $sale, "Réimpression (duplicata) du reçu {$sale->number}");
        }

        return $duplicate;
    }

    private function authorizeSale(Request $request, Sale $sale): void
    {
        $user = $request->user();
        if ($user->hasPermission('sales.view')) {
            return;
        }
        abort_unless($user->hasPermission('sales.view_own') && $sale->user_id === $user->id, 403);
    }
}
