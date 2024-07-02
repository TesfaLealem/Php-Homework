<?php

use PHPUnit\Framework\TestCase;

require 'src/helpers.php';

class BinCheckerTest extends TestCase
{
    public function testIsEuCountry()
    {
        $this->assertTrue(isEuCountry('DE'));
        $this->assertFalse(isEuCountry('US'));
    }

    public function testFetchCountryInfo()
    {
        $countryInfo = fetchCountryInfo('45717360');
        $this->assertEquals('DK', $countryInfo->country->alpha2);
    }

    public function testFetchExchangeRate()
    {
        $rate = fetchExchangeRate('USD');
        $this->assertGreaterThan(0, $rate);
    }

    public function testCalculateAmount()
    {
        $this->assertEquals(1.00, calculateAmount(100.0, 100.0, true));
        $this->assertEquals(2.00, calculateAmount(100.0, 50.0, false));
    }
}
