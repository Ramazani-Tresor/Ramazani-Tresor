<?php

namespace App\Models;

use App\Models\Concerns\Undeletable;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;

/**
 * Droit à une prime pour une période. Formules (RF) :
 *   net    = amount + Σ ajustements
 *   payé   = Σ mouvements validés ou contrepassés + leurs contrepassations (signées) : un mouvement contrepassé
 *            a bien été versé, sa contrepassation (montant opposé) l'annule ; rien n'est supprimé.
 *   solde  = net − payé  (négatif = trop-perçu à régulariser)
 */
class PrimeEntitlement extends Model
{
    use Undeletable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'base_amount' => 'decimal:2', 'amount' => 'decimal:2', 'eligible_ratio' => 'decimal:6', 'calculation' => 'array'];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function primeType()
    {
        return $this->belongsTo(PrimeType::class);
    }

    public function rate()
    {
        return $this->belongsTo(PrimeRate::class, 'prime_rate_id');
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function adjustments()
    {
        return $this->hasMany(PrimeAdjustment::class);
    }

    public function payments()
    {
        return $this->hasMany(PrimePayment::class);
    }

    public function netAmount(): string
    {
        return Money::sum([(string) $this->amount, ...$this->adjustments->pluck('amount')->map(fn ($a) => (string) $a)->all()]);
    }

    public function paidAmount(): string
    {
        return Money::sum($this->payments->whereIn('status', ['validated', 'reversed'])->pluck('amount')->map(fn ($a) => (string) $a)->all());
    }

    /** Acomptes validés et non contrepassés. */
    public function advancesAmount(): string
    {
        return Money::sum($this->payments->where('status', 'validated')->where('kind', 'advance')->pluck('amount')->map(fn ($a) => (string) $a)->all());
    }

    public function pendingAmount(): string
    {
        return Money::sum($this->payments->where('status', 'pending')->pluck('amount')->map(fn ($a) => (string) $a)->all());
    }

    public function balance(): string
    {
        return Money::money(Money::sub($this->netAmount(), $this->paidAmount()));
    }
}
