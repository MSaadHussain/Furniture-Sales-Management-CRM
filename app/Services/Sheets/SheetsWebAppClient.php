<?php

namespace App\Services\Sheets;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Talks to the Apps Script Web App that owns the spreadsheet.
 *
 * The deployment URL is reachable by anyone who knows it, so every request is
 * signed: HMAC-SHA256 over "<timestamp>.<payload>" with a shared secret, and
 * the script rejects anything older than five minutes.
 *
 * Apps Script does not hand `doPost` the request headers, so the timestamp and
 * signature travel inside the body and the payload is sent as an already
 * encoded string. That way both sides sign byte-for-byte the same text instead
 * of trusting two languages to serialise JSON identically.
 */
class SheetsWebAppClient
{
    public function configured(): bool
    {
        return (bool) config('sheets.enabled')
            && $this->urlProblem() === null
            && filled(config('sheets.secret'));
    }

    /**
     * Why the configured URL is unusable, or null when it looks right.
     *
     * Worth its own check: a placeholder or the spreadsheet's own link instead
     * of the /exec deployment URL otherwise surfaces as an opaque Guzzle
     * "scheme is not allowed" error.
     */
    public function urlProblem(): ?string
    {
        $url = (string) config('sheets.url');

        if ($url === '') {
            return 'not set';
        }

        if (! filter_var($url, FILTER_VALIDATE_URL) || ! str_starts_with($url, 'https://')) {
            return 'not a valid https URL';
        }

        if (! str_contains($url, '/macros/s/') || ! str_ends_with($url, '/exec')) {
            return 'this is not an Apps Script Web App URL (it must look like https://script.google.com/macros/s/.../exec)';
        }

        return null;
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

        // Deliberately NOT JSON_UNESCAPED_UNICODE: accented names travel as
        // \uXXXX escapes so the signed text is pure ASCII. Apps Script's
        // computeHmacSha256Signature turns a string into bytes with a charset
        // of its own choosing, which silently disagrees with PHP the moment a
        // byte goes above 0x7F. Keeping the bytes ASCII removes the question;
        // JSON.parse on the other side restores the accents either way.
        $encoded   = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $timestamp = (string) time();

        $envelope = json_encode([
            'ts'      => $timestamp,
            'sig'     => $this->sign($timestamp, $encoded),
            'payload' => $encoded,
        ], JSON_UNESCAPED_SLASHES);

        $response = Http::timeout((int) config('sheets.timeout'))
            ->withHeaders(['Content-Type' => 'application/json'])
            // Apps Script answers a web app with a 302 to googleusercontent.com.
            ->withOptions(['allow_redirects' => ['max' => 5, 'strict' => false]])
            ->send('POST', (string) config('sheets.url'), ['body' => $envelope]);

        return $this->decode($response);
    }

    public function sign(string $timestamp, string $payload): string
    {
        return hash_hmac('sha256', $timestamp . '.' . $payload, (string) config('sheets.secret'));
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
