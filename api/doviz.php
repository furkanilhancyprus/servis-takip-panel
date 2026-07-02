<?php
require_once __DIR__ . '/_base.php';

function fetch_url_text(string $url): ?string {
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 5,
            'header'  => "User-Agent: ServisTakipPanel/1.0\r\n",
        ],
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    return $raw === false ? null : $raw;
}

function tcmb_usd_try(): ?array {
    $xmlText = fetch_url_text('https://www.tcmb.gov.tr/kurlar/today.xml');
    if (!$xmlText) return null;

    if (!function_exists('simplexml_load_string')) {
        if (preg_match('/<Currency[^>]+CurrencyCode="USD"[\s\S]*?<ForexSelling>([^<]+)<\/ForexSelling>/u', $xmlText, $m)) {
            $rate = (float)str_replace(',', '.', trim($m[1]));
            return $rate > 0 ? ['usd_try' => $rate, 'source' => 'TCMB', 'updated_at' => date('c')] : null;
        }
        return null;
    }

    $xml = @simplexml_load_string($xmlText);
    if (!$xml) return null;
    foreach ($xml->Currency as $currency) {
        if ((string)$currency['CurrencyCode'] !== 'USD') continue;
        $rate = (float)str_replace(',', '.', (string)$currency->ForexSelling);
        if ($rate > 0) {
            return ['usd_try' => $rate, 'source' => 'TCMB', 'updated_at' => date('c')];
        }
    }
    return null;
}

function fallback_usd_try(): ?array {
    $json = fetch_url_text('https://open.er-api.com/v6/latest/USD');
    if (!$json) return null;

    $data = json_decode($json, true);
    $rate = (float)($data['rates']['TRY'] ?? 0);
    if ($rate <= 0) return null;

    return [
        'usd_try' => $rate,
        'source' => 'open.er-api.com',
        'updated_at' => isset($data['time_last_update_unix']) ? date('c', (int)$data['time_last_update_unix']) : date('c'),
    ];
}

if (method() !== 'GET') {
    json_err('Desteklenmeyen metod.', 405);
}

$rate = tcmb_usd_try() ?: fallback_usd_try();
if (!$rate) {
    json_err('Döviz kuru alınamadı.', 502);
}

json_ok($rate);
