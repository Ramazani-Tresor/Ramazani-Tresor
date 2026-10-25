<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Support\DbErrors;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Sessions de caisse : une session ouverte au plus par caissier.
 * Montant théorique en espèces = fonds initial + somme des mouvements espèces de la session.
 * Les paiements électroniques sont tracés dans la session mais n'entrent pas dans le tiroir.
 */
class CashService
{
    public function __construct(private NumberingService $numbering) {}

    public function open(User $user, string $openingAmount): CashSession
    {
        if (Money::isNegative($openingAmount)) {
            throw new BusinessException('Le fonds initial ne peut pas être négatif.');
        }

        try {
            return DB::transaction(function () use ($user, $openingAmount) {
                if (CashSession::query()->where('user_id', $user->id)->where('status', 'open')->lockForUpdate()->exists()) {
                    throw new BusinessException('Vous avez déjà une session de caisse ouverte.');
                }
                $session = CashSession::create([
                    'number' => $this->numbering->next('cash_session'),
                    'user_id' => $user->id,
                    'status' => 'open',
                    'opening_amount' => Money::money($openingAmount),
                    'opened_at' => now(),
                    'open_lock' => $user->id,
                ]);
                CashMovement::create([
                    'cash_session_id' => $session->id,
                    'payment_method_id' => PaymentMethod::cash()->id,
                    'type' => 'opening',
                    'amount' => Money::money($openingAmount),
                    'description' => 'Fonds de caisse initial',
                    'user_id' => $user->id,
                ]);
                AuditService::log('cash.open', $session, 'Ouverture de caisse, fonds initial '.Money::format($openingAmount));

                return $session;
            });
        } catch (QueryException $e) {
            // Contrainte unique open_lock : ouverture simultanée par double clic.
            if (DbErrors::isUniqueViolation($e)) {
                throw new BusinessException('Vous avez déjà une session de caisse ouverte.');
            }
            throw $e;
        }
    }

    /** Session ouverte de l'utilisateur, verrouillée pour la transaction en cours. */
    public function requireOpenSession(User $user): CashSession
    {
        $session = CashSession::query()->where('user_id', $user->id)->where('status', 'open')->lockForUpdate()->first();
        if (! $session) {
            throw new BusinessException("Aucune session de caisse ouverte. Ouvrez votre caisse avant d'encaisser.");
        }

        return $session;
    }

    public function record(CashSession $session, PaymentMethod $method, string $type, string $amount, User $user, ?string $description = null, ?Model $source = null): CashMovement
    {
        if (! $session->isOpen()) {
            throw new BusinessException('Cette session de caisse est clôturée : aucune opération n\'est possible.');
        }
        if (Money::isZero($amount)) {
            throw new BusinessException('Montant de caisse nul.');
        }

        return CashMovement::create([
            'cash_session_id' => $session->id,
            'payment_method_id' => $method->id,
            'type' => $type,
            'amount' => Money::money($amount),
            'description' => $description,
            'source_type' => $source?->getMorphClass(),
            'source_id' => $source?->getKey(),
            'user_id' => $user->id,
        ]);
    }

    /** Apport ou retrait manuel d'espèces. */
    public function manual(User $user, string $type, string $amount, string $description): CashMovement
    {
        if (! in_array($type, ['deposit', 'withdrawal'], true) || ! Money::isPositive($amount)) {
            throw new BusinessException('Opération de caisse invalide.');
        }

        return DB::transaction(function () use ($user, $type, $amount, $description) {
            $session = $this->requireOpenSession($user);
            $signed = $type === 'withdrawal' ? '-'.Money::money($amount) : Money::money($amount);
            if ($type === 'withdrawal' && Money::cmp($this->expectedCash($session), $amount) < 0) {
                throw new BusinessException('Retrait impossible : le montant dépasse les espèces théoriquement disponibles.');
            }
            $mv = $this->record($session, PaymentMethod::cash(), $type, $signed, $user, $description);
            AuditService::log('cash.'.$type, $session, CashMovement::TYPES[$type].' : '.Money::format($amount).' — '.$description);

            return $mv;
        });
    }

    public function expectedCash(CashSession $session): string
    {
        $sum = CashMovement::query()
            ->where('cash_session_id', $session->id)
            ->whereHas('paymentMethod', fn ($q) => $q->where('kind', 'cash'))
            ->sum('amount');

        return Money::money($sum);
    }

    public function electronicTotal(CashSession $session): string
    {
        $sum = CashMovement::query()
            ->where('cash_session_id', $session->id)
            ->whereHas('paymentMethod', fn ($q) => $q->where('kind', 'electronic'))
            ->sum('amount');

        return Money::money($sum);
    }

    /** @return array<int, array{method: string, kind: string, total: string}> */
    public function totalsByMethod(CashSession $session): array
    {
        return CashMovement::query()
            ->where('cash_session_id', $session->id)
            ->join('payment_methods', 'payment_methods.id', '=', 'cash_movements.payment_method_id')
            ->groupBy('payment_methods.name', 'payment_methods.kind')
            ->selectRaw('payment_methods.name as method, payment_methods.kind as kind, SUM(cash_movements.amount) as total')
            ->get()
            ->map(fn ($r) => ['method' => $r->method, 'kind' => $r->kind, 'total' => Money::money($r->total)])
            ->all();
    }

    public function close(CashSession $session, string $countedCash, ?string $notes, User $closer): CashSession
    {
        if (Money::isNegative($countedCash)) {
            throw new BusinessException('Le montant compté ne peut pas être négatif.');
        }

        return DB::transaction(function () use ($session, $countedCash, $notes, $closer) {
            $session = CashSession::query()->lockForUpdate()->findOrFail($session->id);
            if (! $session->isOpen()) {
                throw new BusinessException('Cette session de caisse est déjà clôturée.');
            }
            $expected = $this->expectedCash($session);
            $difference = Money::of($countedCash)->minus(Money::of($expected));
            if (! $difference->isZero() && trim((string) $notes) === '') {
                throw new BusinessException('Un écart de caisse de '.Money::format($difference).' est constaté : la justification est obligatoire.');
            }
            $session->update([
                'status' => 'closed',
                'expected_cash' => $expected,
                'counted_cash' => Money::money($countedCash),
                'difference' => Money::money($difference),
                'electronic_total' => $this->electronicTotal($session),
                'closing_notes' => $notes,
                'closed_at' => now(),
                'closed_by' => $closer->id,
                'open_lock' => null,
            ]);
            AuditService::log('cash.close', $session, 'Clôture : théorique '.Money::format($expected).', compté '.Money::format($countedCash).', écart '.Money::format($difference));

            return $session;
        });
    }
}
