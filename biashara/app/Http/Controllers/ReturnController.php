<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessException;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\User;
use App\Services\ReturnService;
use App\Support\Money;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ReturnController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAny(['returns.create', 'sales.view']);
        [$from, $to] = $this->period($request);
        $returns = SaleReturn::query()->with(['sale', 'user', 'authorizer'])
            ->whereBetween('returned_at', [$from, $to])
            ->when(! $request->user()->hasPermission('sales.view'), fn ($q) => $q->where('user_id', $request->user()->id))
            ->latest('returned_at')->paginate(25)->withQueryString();

        return view('returns.index', compact('returns', 'from', 'to'));
    }

    public function create(Request $request, Sale $sale)
    {
        if ($sale->isCancelled()) {
            return redirect()->route('sales.show', $sale)->with('error', 'Cette vente est annulée.');
        }

        return view('returns.create', [
            'sale' => $sale->load(['items', 'customer']),
            'paymentMethods' => PaymentMethod::query()->active()->get(),
            'needsSupervisor' => ! $request->user()->hasPermission('returns.authorize'),
        ]);
    }

    public function store(Request $request, Sale $sale, ReturnService $service)
    {
        $data = $request->validate([
            'client_uuid' => 'required|uuid',
            'reason' => 'required|string|max:255',
            'lines' => 'required|array',
            'lines.*.quantity' => 'nullable|numeric|min:0',
            'lines.*.condition' => 'required|in:resaleable,damaged',
            'refund_amount' => 'nullable|numeric|min:0',
            'refund_method_id' => 'nullable|exists:payment_methods,id',
            'supervisor_username' => 'nullable|string',
            'supervisor_password' => 'nullable|string',
        ]);

        // Un utilisateur sans droit d'autorisation doit faire valider le retour par un responsable (identifiants).
        $authorizer = $request->user();
        if (! $authorizer->hasPermission('returns.authorize')) {
            $supervisor = User::query()->where('username', $data['supervisor_username'] ?? '')->where('is_active', true)->first();
            if (! $supervisor || ! Hash::check($data['supervisor_password'] ?? '', $supervisor->password) || ! $supervisor->hasPermission('returns.authorize')) {
                throw new BusinessException('Autorisation refusée : identifiants du responsable invalides ou sans droit d\'autoriser les retours.');
            }
            $authorizer = $supervisor;
        }

        $lines = [];
        foreach ($data['lines'] as $itemId => $line) {
            if (Money::isPositive($line['quantity'] ?? 0)) {
                $lines[] = ['sale_item_id' => (int) $itemId, 'quantity' => (string) $line['quantity'], 'condition' => $line['condition']];
            }
        }
        $return = $service->create(
            $sale, $lines, $data['reason'], $request->user(), $authorizer,
            isset($data['refund_amount']) && $data['refund_amount'] !== '' ? (string) $data['refund_amount'] : null,
            isset($data['refund_method_id']) ? PaymentMethod::find($data['refund_method_id']) : null,
            $data['client_uuid'],
        );

        return redirect()->route('returns.show', $return)->with('success', 'Retour enregistré.');
    }

    public function show(Request $request, SaleReturn $saleReturn)
    {
        $this->authorizeAny(['returns.create', 'sales.view']);

        return view('receipts.return', [
            'return' => $saleReturn->load(['items.saleItem', 'sale.customer', 'user', 'authorizer', 'refundMethod']),
            'shop' => Settings::all(),
            'format' => in_array($request->format, ['a4', '80', '58'], true) ? $request->format : Settings::get('receipt_format', '80'),
        ]);
    }
}
