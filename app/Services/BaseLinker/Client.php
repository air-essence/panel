<?php

namespace App\Services\BaseLinker;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Klient API BaseLinkera. Celowo pozwala wyłącznie na metody odczytu (get*),
 * dopóki właściciel projektu nie zdecyduje o zapisie do BaseLinkera.
 */
class Client
{
    public function __construct(
        private readonly ?string $token,
        private readonly string $url,
    ) {}

    public static function fromConfig(): self
    {
        return new self(config('services.baselinker.token'), config('services.baselinker.url'));
    }

    public function isConfigured(): bool
    {
        return filled($this->token);
    }

    /**
     * @return array<string, mixed>
     */
    public function call(string $method, array $parameters = []): array
    {
        if (! str_starts_with($method, 'get')) {
            throw new BaseLinkerException("Metoda {$method} zmienia dane w BaseLinkerze. Panel działa tylko w trybie odczytu.");
        }

        if (! $this->isConfigured()) {
            throw new BaseLinkerException('Brak tokenu BaseLinkera. Ustaw BASELINKER_TOKEN w pliku .env.');
        }

        try {
            $response = Http::asForm()
                ->withHeaders(['X-BLToken' => $this->token])
                ->timeout(30)
                ->retry(2, 2000, throw: false)
                ->post($this->url, [
                    'method' => $method,
                    'parameters' => json_encode((object) $parameters),
                ]);
        } catch (ConnectionException $e) {
            throw new BaseLinkerException('Brak połączenia z BaseLinkerem: '.$e->getMessage(), previous: $e);
        }

        $data = $response->json();

        if (! $response->successful() || ! is_array($data)) {
            throw new BaseLinkerException("BaseLinker odpowiedział błędem HTTP {$response->status()}.");
        }

        if (($data['status'] ?? null) !== 'SUCCESS') {
            $code = $data['error_code'] ?? 'nieznany';
            $message = $data['error_message'] ?? 'brak opisu';

            throw new BaseLinkerException("BaseLinker zwrócił błąd {$code}: {$message}");
        }

        return $data;
    }
}
