<?php

namespace App\Services\Sheets;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Talks to the Apps Script Web App that owns the spreadsheet.
 *
 * The deployment URL is reachable by anyone who knows it, so every request is
 * signed: HMAC-SHA256 over "<timestamp>.<raw body>" with a shared secret, and
 * the script rejects anything older than five minutes. Same scheme as the
 * HMAC service-to-service auth used elsewhere.
 */
class SheetsWebAppClient
{
    public function configured(): bool
    {
        return (bool) config('sheets.enabled')
            && filled(config('sheets.url'))
            && filled(config('sheets.secret'));
    }

    /** Round-trip check used by `sheets:status`. */
    public function ping(): array
    {
        return $this->post(['action' => 'ping']);
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    public function post(array $payload): array
    {
        if (! $this->configured()) {
            throw new RuntimeException('Google Sheets sync is not configured.');
        }

        $body      = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $timestamp = (string) time();

        $response = Http::timeout((int) config('sheets.timeout'))
            ->withHeaders([
                'Content-Type'       => 'application/json',
                'X-Sync-Timestamp'   => $timestamp,
                'X-Sync-Signature'   => $this->sign($timestamp, $body),
            ])
            // Apps Script answers a web app with a 302 to googleusercontent.com.
            ->withOptions(['allow_redirects' => ['max' => 5, 'strict' => false]])
            ->send('POST', (string) config('sheets.url'), ['body' => $body]);

        return $this->decode($response);
    }

    public function sign(string $timestamp, string $body): string
    {
        return hash_hmac('sha256', $timestamp . '.' . $body, (string) config('sheets.secret'));
    }

    /** @return array<string,mixed> */
    private function decode(Response $response): array
    {
        if ($response->failed()) {
            throw new RuntimeException(
                "Apps Script returned HTTP {$response->status()}: " . mb_substr($response->body(), 0, 300)
            );
        }

        $data = $response->json();

        if (! is_array($data)) {
            // A login page instead of JSON almost always means the deployment
            // is not set to "Anyone" access.
            throw new RuntimeException(
                'Apps Script did not return JSON. Check that the Web App is deployed with access "Anyone". Got: '
                . mb_substr($response->body(), 0, 200)
            );
        }

        if (($data['ok'] ?? false) !== true) {
            throw new RuntimeException('Apps Script rejected the request: ' . ($data['error'] ?? 'unknown error'));
        }

        return $data;
    }
}
