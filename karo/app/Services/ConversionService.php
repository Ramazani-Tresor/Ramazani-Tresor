<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\ExchangeRate;
use App\Support\Money;

/** Conversion entre devises : uniquement avec un taux approuvé et enregistré. */
class ConversionService
{
    /** @return array{0:string, 1:?ExchangeRate} montant converti et taux utilisé */
    public function convert(string $amount, string $from, string $to, ?string $date = null): array
    {
        if ($from === $to) {
            return [Money::money($amount), null];
        }
        $rate = ExchangeRate::query()->where('from_currency', $from)->where('to_currency', $to)
            ->whereDate('effective_on', '<=', $date ?? now()->toDateString())->orderByDesc('effective_on')->first();
        if (! $rate) {
            throw new BusinessException("Aucun taux de conversion approuvé de {$from} vers {$to} : opération entre devises impossible.");
        }

        return [Money::money(Money::mul($amount, $rate->rate)), $rate];
    }
}
