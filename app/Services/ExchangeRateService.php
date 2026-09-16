<?php
namespace MyOrdersVault\Services;

use MyOrdersVault\Config\Database;

class ExchangeRateService {
    // Free, keyless feed — rates refresh daily upstream anyway, so caching
    // for a day keeps every page load from hitting the network.
    const CACHE_TTL_HOURS = 24;
    const API_URL = 'https://open.er-api.com/v6/latest/USD';

    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    // "1 USD = X <currency>". Returns null only if nothing is cached and the
    // live fetch also fails — callers should then skip conversion rather
    // than show a made-up number.
    public function getRateToUsd($currency) {
        $currency = strtoupper($currency);
        if ($currency === 'USD') {
            return 1.0;
        }

        $stmt = $this->db->prepare("SELECT rate_to_usd, fetched_at FROM exchange_rates WHERE currency = :currency");
        $stmt->execute(['currency' => $currency]);
        $row = $stmt->fetch();

        $isStale = !$row || strtotime($row['fetched_at']) < (time() - self::CACHE_TTL_HOURS * 3600);
        if ($isStale) {
            $this->refresh();
            $stmt->execute(['currency' => $currency]);
            $freshRow = $stmt->fetch();
            if ($freshRow) {
                $row = $freshRow;
            }
        }

        return $row ? (float) $row['rate_to_usd'] : null;
    }

    // Returns null if either currency's rate is unavailable (never fetched
    // successfully, and no cached fallback exists).
    public function convert($amount, $from, $to) {
        $from = strtoupper($from);
        $to = strtoupper($to);
        if ($from === $to) {
            return (float) $amount;
        }

        $rateFrom = $this->getRateToUsd($from);
        $rateTo = $this->getRateToUsd($to);
        if ($rateFrom === null || $rateTo === null || $rateFrom == 0) {
            return null;
        }

        $usd = $amount / $rateFrom;
        return $usd * $rateTo;
    }

    // Best-effort refresh from open.er-api.com. Failures are swallowed —
    // getRateToUsd() falls back to whatever was last cached (or null if
    // nothing ever succeeded).
    private function refresh() {
        try {
            $context = stream_context_create(['http' => ['timeout' => 5]]);
            $json = @file_get_contents(self::API_URL, false, $context);
            if ($json === false) {
                return;
            }

            $data = json_decode($json, true);
            if (($data['result'] ?? null) !== 'success' || empty($data['rates'])) {
                return;
            }

            $now = date('Y-m-d H:i:s');
            $stmt = $this->db->prepare("
                INSERT INTO exchange_rates (currency, rate_to_usd, fetched_at)
                VALUES (:currency, :rate, :fetched_at)
                ON DUPLICATE KEY UPDATE rate_to_usd = :rate2, fetched_at = :fetched_at2
            ");
            foreach ($data['rates'] as $currency => $rate) {
                $stmt->execute([
                    'currency' => $currency,
                    'rate' => $rate,
                    'fetched_at' => $now,
                    'rate2' => $rate,
                    'fetched_at2' => $now,
                ]);
            }
        } catch (\Throwable $e) {
            error_log('[ExchangeRateService] refresh failed: ' . $e->getMessage());
        }
    }
}
