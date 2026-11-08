<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_calculs_decimaux_exacts_sans_flottant(): void
    {
        // 0,1 + 0,2 vaut exactement 0,3 (contrairement aux flottants)
        $this->assertSame('0.30', Money::money(Money::add('0.1', '0.2')));
        $this->assertSame('1044.00', Money::money(Money::of('900')->plus(Money::percent('900', '16'))));
        $this->assertSame('0.01', Money::money(Money::mul('0.005', '1'))); // arrondi demi supérieur
        $this->assertSame('333.3333', Money::cost(Money::div('1000', '3')));
    }

    public function test_formatage_francais(): void
    {
        $this->assertSame("1\u{202F}234\u{202F}567,50 FC", Money::format('1234567.5', 'FC'));
        $this->assertSame('-500,00 $', Money::format('-500', '$'));
        $this->assertSame('2,5', Money::formatQty('2.500'));
        $this->assertSame('3', Money::formatQty('3.000'));
    }
}
