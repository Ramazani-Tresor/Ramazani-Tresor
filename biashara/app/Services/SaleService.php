<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\CashSession;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Support\DbErrors;
use App\Support\Money;
use App\Support\Settings;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Validation d'une vente : lignes, paiements, mouvements de stock et de caisse sont
 * créés dans UNE transaction. L'identifiant `client_uuid` (généré par l'appareil)
 * rend l'opération idempotente : un double clic ou une re-tentative renvoie la vente existante.
 */
class SaleService
{
    public function __construct(
        private StockService $stock,
        private CashService $cash,
        private NumberingService $numbering,
    ) {}

    /**
     * @param  array{
     *   client_uuid: string,
     *   customer_id?: int|null,
     *   items: array<int, array{product_id:int, quantity:string|int|float, discount_amount?:mixed, unit_price?:mixed}>,
     *   payments?: array<int, array{payment_method_id:int, amount?:mixed, currency?:string, currency_amount?:mixed, reference?:string|null}>,
     *   notes?: string|null,
     *   origin?: string,
     *   device_id?: string|null,
     *   sold_at?: string|null,
     * }  $data
     * @param  bool  $useGivenPrices  prix du ticket (vente hors ligne acceptée) au lieu du prix catalogue
     * @return array{0: Sale, 1: bool} [vente, true si elle existait déjà]
     */
    public function create(array $data, User $seller, ?CashSession $session = null, bool $useGivenPrices = false): array
    {
        $uuid = (string) ($data['client_uuid'] ?? '');
        if ($uuid === '') {
            throw new BusinessException('Identifiant de transaction manquant.');
        }
        if ($existing = Sale::query()->where('client_uuid', $uuid)->first()) {
            return [$existing, true];
        }

        try {
            $sale = DB::transaction(fn () => $this->doCreate($data, $seller, $session, $useGivenPrices));
        } catch (QueryException $e) {
            if (DbErrors::isUniqueViolation($e) && ($existing = Sale::query()->where('client_uuid', $uuid)->first())) {
                return [$existing, true];
            }
            throw $e;
        }

        return [$sale, false];
    }

    private function doCreate(array $data, User $seller, ?CashSession $session, bool $useGivenPrices): Sale
    {
        $items = $data['items'] ?? [];
        if (count($items) === 0) {
            throw new BusinessException('Le panier est vide.');
        }

        $session = $session
            ? CashSession::query()->lockForUpdate()->findOrFail($session->id)
            : $this->cash->requireOpenSession($seller);
        if (! $session->isOpen()) {
            throw new BusinessException('La session de caisse est clôturée : la vente ne peut pas y être rattachée.');
        }

        $customer = null;
        if (! empty($data['customer_id'])) {
            $customer = Customer::query()->lockForUpdate()->find($data['customer_id']);
            if (! $customer || ! $customer->is_active) {
                throw new BusinessException('Client introuvable ou inactif.');
            }
        }

        // Verrouillage des produits : deux caissiers ne peuvent pas vendre le même dernier article.
        $products = $this->stock->lockProducts(array_column($items, 'product_id'));
        $maxDiscount = Money::of(Settings::get('max_discount_percent', '0'));
        $canDiscount = $seller->hasPermission('sales.discount');

        $lines = [];
        $subtotal = BigDecimal::zero();
        $discountTotal = BigDecimal::zero();
        $taxTotal = BigDecimal::zero();
        $total = BigDecimal::zero();

        foreach ($items as $i => $item) {
            /** @var Product|null $product */
            $product = $products->get((int) $item['product_id']);
            $n = $i + 1;
            if (! $product) {
                throw new BusinessException("Ligne {$n} : article introuvable.");
            }
            if (! $product->is_active) {
                throw new BusinessException("« {$product->name} » est inactif et ne peut pas être vendu.");
            }
            $qty = Money::of(Money::qty($item['quantity'] ?? 0));
            if (! $qty->isPositive()) {
                throw new BusinessException("Ligne {$n} : la quantité doit être positive.");
            }
            if (! $product->unit?->allows_decimal && ! $qty->isEqualTo($qty->toScale(0, RoundingMode::DOWN))) {
                throw new BusinessException("« {$product->name} » se vend uniquement en quantités entières.");
            }

            $unitPrice = $useGivenPrices && isset($item['unit_price'])
                ? Money::of(Money::money($item['unit_price']))
                : Money::of($product->sale_price);
            $gross = Money::mul($qty, $unitPrice);
            $discount = Money::of(Money::money($item['discount_amount'] ?? 0));
            if ($discount->isNegative()) {
                throw new BusinessException("Ligne {$n} : remise invalide.");
            }
            if ($discount->isPositive()) {
                if (! $canDiscount) {
                    throw new BusinessException("Vous n'êtes pas autorisé à accorder des remises.");
                }
                if (Money::cmp($discount, $gross) > 0) {
                    throw new BusinessException("Ligne {$n} : la remise dépasse le montant de la ligne.");
                }
                $ceiling = Money::percent($gross, $maxDiscount);
                if (! $seller->isAdmin() && Money::cmp($discount, $ceiling) > 0) {
                    throw new BusinessException("Ligne {$n} : la remise dépasse le plafond autorisé de {$maxDiscount} %.");
                }
            }
            $net = $gross->minus($discount);
            $taxRate = $product->tax && $product->tax->is_active ? Money::of($product->tax->rate) : BigDecimal::zero();
            $tax = Money::percent($net, $taxRate);
            $lineTotal = $net->plus($tax);

            $lines[] = compact('product', 'qty', 'unitPrice', 'discount', 'taxRate', 'tax', 'lineTotal');
            $subtotal = $subtotal->plus($gross);
            $discountTotal = $discountTotal->plus($discount);
            $taxTotal = $taxTotal->plus($tax);
            $total = $total->plus($lineTotal);
        }

        // ---- Paiements ----
        $currency = Settings::get('currency_code', 'CDF');
        $secondary = Settings::bool('secondary_currency_enabled') ? Settings::get('secondary_currency_code') : null;
        $rate = Money::of(Settings::get('exchange_rate', '1'));
        $methods = PaymentMethod::query()->where('is_active', true)->get()->keyBy('id');

        $payments = [];
        $received = BigDecimal::zero();
        $cashReceived = BigDecimal::zero();
        $electronic = BigDecimal::zero();
        foreach ($data['payments'] ?? [] as $p) {
            $method = $methods->get((int) ($p['payment_method_id'] ?? 0));
            if (! $method) {
                throw new BusinessException('Mode de paiement invalide ou désactivé.');
            }
            $payCurrency = $p['currency'] ?? $currency;
            if ($payCurrency !== $currency) {
                if ($payCurrency !== $secondary) {
                    throw new BusinessException("Devise {$payCurrency} non acceptée.");
                }
                $currencyAmount = Money::of(Money::money($p['currency_amount'] ?? 0));
                $amount = Money::mul($currencyAmount, $rate);
                $payRate = $rate;
            } else {
                $amount = Money::of(Money::money($p['amount'] ?? 0));
                $currencyAmount = $amount;
                $payRate = BigDecimal::one();
            }
            if ($amount->isZero()) {
                continue;
            }
            if ($amount->isNegative()) {
                throw new BusinessException('Un montant payé ne peut pas être négatif.');
            }
            $payments[] = compact('method', 'amount', 'currencyAmount', 'payCurrency', 'payRate') + ['reference' => $p['reference'] ?? null];
            $received = $received->plus($amount);
            $method->isCash() ? $cashReceived = $cashReceived->plus($amount) : $electronic = $electronic->plus($amount);
        }

        if (Money::cmp($electronic, $total) > 0) {
            throw new BusinessException('Les paiements électroniques dépassent le net à payer : la monnaie ne peut être rendue qu\'en espèces.');
        }
        $change = Money::max(0, $received->minus($total));
        $paid = Money::min($received, $total);
        $due = $total->minus($paid);

        if ($due->isPositive()) {
            if (! $seller->hasPermission('sales.credit')) {
                throw new BusinessException('Paiement insuffisant : il manque '.Money::format($due).'. Vous n\'êtes pas autorisé à vendre à crédit.');
            }
            if (! $customer) {
                throw new BusinessException('Une vente à crédit ou un paiement partiel exige un client identifié.');
            }
            if ($customer->credit_limit !== null) {
                $exposure = Money::of($customer->balanceDue())->plus($due);
                if (Money::cmp($exposure, $customer->credit_limit) > 0) {
                    throw new BusinessException('Plafond de crédit du client dépassé ('.Money::format($customer->credit_limit).').');
                }
            }
        }

        $soldAt = isset($data['sold_at']) && $data['sold_at'] ? Carbon::parse($data['sold_at']) : now();

        $sale = Sale::create([
            'number' => $this->numbering->next('sale'),
            'client_uuid' => $data['client_uuid'],
            'customer_id' => $customer?->id,
            'user_id' => $seller->id,
            'cash_session_id' => $session->id,
            'status' => 'validated',
            'origin' => ($data['origin'] ?? 'online') === 'offline' ? 'offline' : 'online',
            'device_id' => $data['device_id'] ?? null,
            'subtotal' => Money::money($subtotal),
            'discount_total' => Money::money($discountTotal),
            'tax_total' => Money::money($taxTotal),
            'total' => Money::money($total),
            'amount_paid' => Money::money($paid),
            'amount_due' => Money::money($due),
            'amount_received' => Money::money($received),
            'change_given' => Money::money($change),
            'currency' => $currency,
            'exchange_rate' => '1',
            'notes' => $data['notes'] ?? null,
            'sold_at' => $soldAt,
        ]);

        foreach ($lines as $l) {
            /** @var Product $product */
            $product = $l['product'];
            $unitCost = $product->isService() ? Money::of($product->cost_price) : Money::of($product->average_cost);
            $sale->items()->create([
                'product_id' => $product->id,
                'product_reference' => $product->reference,
                'product_name' => $product->name,
                'product_type' => $product->type,
                'unit_symbol' => $product->unit?->symbol ?? '',
                'quantity' => Money::qty($l['qty']),
                'unit_price' => Money::money($l['unitPrice']),
                'discount_amount' => Money::money($l['discount']),
                'tax_name' => $l['taxRate']->isZero() ? null : $product->tax?->name,
                'tax_rate' => (string) $l['taxRate'],
                'tax_amount' => Money::money($l['tax']),
                'line_total' => Money::money($l['lineTotal']),
                'unit_cost' => Money::cost($unitCost),
            ]);
            if (! $product->isService()) {
                $this->stock->move($product, 'sale', '-'.Money::qty($l['qty']), $seller, $sale, $sale->number, 'Vente', 'available', Money::cost($unitCost));
            }
        }

        // La monnaie rendue est déduite des espèces : seul le net conservé est enregistré.
        $changeLeft = $change;
        foreach ($payments as $p) {
            $amount = $p['amount'];
            $currencyAmount = $p['currencyAmount'];
            if ($p['method']->isCash() && $changeLeft->isPositive()) {
                $deduct = Money::min($changeLeft, $amount);
                $amount = $amount->minus($deduct);
                $changeLeft = $changeLeft->minus($deduct);
                $currencyAmount = $p['payCurrency'] === $currency ? $amount : $currencyAmount; // devise étrangère : monnaie rendue en devise principale
            }
            if ($amount->isZero()) {
                continue;
            }
            $payment = Payment::create([
                'kind' => 'sale',
                'sale_id' => $sale->id,
                'customer_id' => $customer?->id,
                'payment_method_id' => $p['method']->id,
                'cash_session_id' => $session->id,
                'user_id' => $seller->id,
                'amount' => Money::money($amount),
                'currency' => $p['payCurrency'],
                'currency_amount' => Money::money($currencyAmount),
                'exchange_rate' => Money::cost($p['payRate']),
                'reference' => $p['reference'],
                'paid_at' => $soldAt,
            ]);
            $this->cash->record($session, $p['method'], 'sale', Money::money($amount), $seller, 'Vente '.$sale->number, $payment);
        }

        AuditService::log('sale.create', $sale, "Vente {$sale->number} : ".Money::format($total).($due->isPositive() ? ', reste dû '.Money::format($due) : ''), null, null, $seller->id);

        return $sale;
    }

    /**
     * Annulation : la vente n'est jamais supprimée. On génère un retour total (remise en stock,
     * remboursement de ce qui a été payé, extinction de la créance) puis on marque la vente annulée.
     */
    public function cancel(Sale $sale, string $reason, User $user, ?PaymentMethod $refundMethod = null): Sale
    {
        return app(ReturnService::class)->cancelSale($sale, $reason, $user, $refundMethod);
    }
}
