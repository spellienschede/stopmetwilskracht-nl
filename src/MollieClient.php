<?php
declare(strict_types=1);

namespace Grippartner;

/**
 * Dunne Mollie API v2-client (cURL).
 * Bij beschikbare Composer-installatie van mollie/mollie-api-php kan die later worden aangesloten;
 * deze client vermijdt PSR-afhankelijkheden en houdt secrets buiten de repo.
 */
final class MollieClient
{
    private string $apiKey;

    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey ?? Config::string('MOLLIE_API_KEY');
        if ($this->apiKey === '') {
            throw new \RuntimeException('MOLLIE_API_KEY ontbreekt in de configuratie.');
        }
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function createPayment(array $payload): array
    {
        return $this->request('POST', '/v2/payments', $payload);
    }

    /** @return array<string,mixed> */
    public function getPayment(string $paymentId): array
    {
        return $this->request('GET', '/v2/payments/' . rawurlencode($paymentId));
    }

    /**
     * @param array<string,mixed>|null $body
     * @return array<string,mixed>
     */
    private function request(string $method, string $path, ?array $body = null): array
    {
        $verifySsl = Config::bool('MOLLIE_SSL_VERIFY', Config::string('APP_ENV') !== 'local');
        $result = $this->execute($method, $path, $body, $verifySsl);

        // Laragon/Windows: CA-bundle faalt soms (errno 60) — één retry zonder verify
        if ($result['errno'] === 60 && $verifySsl) {
            Logger::error('Mollie SSL verify failed, retry without verify', [
                'error' => $result['error'],
            ]);
            $result = $this->execute($method, $path, $body, false);
        }

        if ($result['errno'] !== 0 || $result['raw'] === false) {
            Logger::error('Mollie cURL error', ['errno' => $result['errno'], 'error' => $result['error']]);
            throw new \RuntimeException('Kon geen verbinding maken met Mollie.');
        }

        $decoded = json_decode((string) $result['raw'], true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Ongeldig antwoord van Mollie.');
        }

        if ($result['status'] >= 400) {
            $detail = $decoded['detail'] ?? ($decoded['title'] ?? 'Mollie-fout');
            Logger::error('Mollie API error', ['status' => $result['status'], 'detail' => (string) $detail]);
            throw new \RuntimeException('Betaaldienst gaf een fout: ' . (string) $detail);
        }

        return $decoded;
    }

    /**
     * @param array<string,mixed>|null $body
     * @return array{raw:string|false,errno:int,error:string,status:int}
     */
    private function execute(string $method, string $path, ?array $body, bool $verifySsl): array
    {
        $url = 'https://api.mollie.com' . $path;
        $ch = curl_init($url);
        if ($ch === false) {
            return ['raw' => false, 'errno' => -1, 'error' => 'curl_init failed', 'status' => 0];
        }

        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Accept: application/json',
            'Content-Type: application/json',
            'User-Agent: GrippartnerNL/1.0',
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        if (!$verifySsl) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        } else {
            $ca = Config::string('CURL_CAINFO');
            if ($ca !== '' && is_readable($ca)) {
                curl_setopt($ch, CURLOPT_CAINFO, $ca);
            }
        }

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, \CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'raw' => $raw,
            'errno' => $errno,
            'error' => $error,
            'status' => $status,
        ];
    }
}
