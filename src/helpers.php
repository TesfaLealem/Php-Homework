<?php

function fetchCountryInfo(string $bin): stdClass
{
    $binResults = file_get_contents('https://lookup.binlist.net/' . $bin);
    if (!$binResults) {
        throw new Exception('Error fetching country information.');
    }
    return json_decode($binResults);
}

function fetchExchangeRate(string $currency): float
{
    $rateResponse = file_get_contents('https://api.exchangeratesapi.io/latest');
    if (!$rateResponse) {
        throw new Exception('Error fetching exchange rate.');
    }
    $rateData = json_decode($rateResponse, true);
    $rate = $rateData['rates'][$currency] ?? 0.0;
    return $rate;
}

function calculateAmount(float $amount, float $exchangeRate, bool $isEuCountry): float
{
    $finalAmount = $amount;
    if ($exchangeRate > 0) {
        $finalAmount /= $exchangeRate;
    }
    $finalAmount *= $isEuCountry ? 0.01 : 0.02;
    return ceil($finalAmount * 100) / 100; // Apply ceiling to the nearest cent
}

function isEuCountry(string $countryCode): bool
{
    $euCountries = [
        'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI',
        'FR', 'GR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT',
        'NL', 'PO', 'PT', 'RO', 'SE', 'SI', 'SK'
    ];
    return in_array($countryCode, $euCountries);
}
