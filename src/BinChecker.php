<?php

namespace src;

class BinChecker
{
    private const EU_COUNTRIES = [
        'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI',
        'FR', 'GR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT',
        'NL', 'PO', 'PT', 'RO', 'SE', 'SI', 'SK'
    ];

    private const API_ENDPOINTS = [
        'bin_lookup' => 'https://lookup.binlist.net/%s',
        'exchange_rates' => 'https://api.exchangeratesapi.io/latest'
    ];

    private $cache = [];
    private $exchangeRates = [];

    public function processTransactions(string $inputFile): void
    {
        foreach ($this->readTransactionsFromFile($inputFile) as $transaction) {
            try {
                $this->processTransaction($transaction);
            } catch (\Exception $e) {
                echo "Error processing transaction: " . $e->getMessage() . "\n";
            }
        }
    }

    private function readTransactionsFromFile(string $inputFile): array
    {
        $transactions = [];
        foreach (explode("\n", file_get_contents($inputFile)) as $row) {
            if (empty($row)) {
                break;
            }
            $transaction = $this->parseTransactionRow($row);
            if ($transaction !== null) {
                $transactions[] = $transaction;
            }
        }
        return $transactions;
    }

    private function parseTransactionRow(string $row): ?array
    {
        $data = json_decode($row, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo "Error decoding JSON: " . json_last_error_msg() . "\n";
            return null;
        }
        if (!isset($data['bin'], $data['amount'], $data['currency'])) {
            echo "Invalid transaction data: $row\n";
            return null;
        }
        return $data;
    }

    private function processTransaction(array $transaction): void
    {
        $binInfo = $this->getBinInfo($transaction['bin']);
        $isEu = $this->isEuCountry($binInfo->country->alpha2 ?? '');
        $exchangeRate = $this->getExchangeRate($transaction['currency']);
        $amount = $this->calculateAmount($transaction['amount'], $exchangeRate, $isEu);
        echo $amount . "\n";
    }

    private function getBinInfo(string $bin): \stdClass
    {
        if (isset($this->cache[$bin])) {
            return $this->cache[$bin];
        }

        $retries = 5;
        $waitTime = 1; // seconds

        for ($i = 0; $i < $retries; $i++) {
            $response = @file_get_contents(sprintf(self::API_ENDPOINTS['bin_lookup'], $bin));
            if ($response !== false) {
                $binInfo = json_decode($response);
                $this->cache[$bin] = $binInfo;
                return $binInfo;
            }

            $http_response_header = $http_response_header ?? [];
            if (isset($http_response_header[0]) && strpos($http_response_header[0], '429') !== false) {
                echo "Rate limit exceeded. Retrying in $waitTime seconds...\n";
            } else {
                echo "Error fetching BIN information: " . implode("\n", $http_response_header) . "\n";
            }

            if ($i < $retries - 1) {
                sleep($waitTime);
                $waitTime *= 2; // Exponential backoff
            }
        }

        throw new \Exception("Error fetching BIN information");
    }

    private function getExchangeRate(string $currency): float
    {
        if (empty($this->exchangeRates)) {
            $this->fetchExchangeRates();
        }

        if ($currency === 'EUR' || !isset($this->exchangeRates[$currency])) {
            return 1.0;
        }

        return $this->exchangeRates[$currency];
    }

    private function fetchExchangeRates(): void
    {
        $retries = 5;
        $waitTime = 1; // seconds

        for ($i = 0; $i < $retries; $i++) {
            try {
                $response = @file_get_contents(self::API_ENDPOINTS['exchange_rates']);
                if ($response === false) {
                    throw new \Exception("Error fetching exchange rates");
                }

                $data = json_decode($response, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception("Error decoding exchange rates: " . json_last_error_msg());
                }

                if (isset($data['rates'])) {
                    $this->exchangeRates = $data['rates'];
                    return;
                } else {
                    throw new \Exception("Exchange rates data not found.");
                }
            } catch (\Exception $e) {
                echo "Exception occurred while fetching exchange rates: " . $e->getMessage() . "\n";

                if ($i < $retries - 1) {
                    sleep($waitTime);
                    $waitTime *= 2; // Exponential backoff
                }
            }
        }

        throw new \Exception("Failed to fetch exchange rates after $retries attempts.");
    }


    private function calculateAmount(float $amount, float $exchangeRate, bool $isEu): float
    {
        $amountInEur = $amount / $exchangeRate;
        return $amountInEur * ($isEu ? 0.01 : 0.02);
    }

    private function isEuCountry(string $countryCode): bool
    {
        return in_array($countryCode, self::EU_COUNTRIES);
    }
}
