<?php

namespace App\Services\Pixelforge;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PixelforgeClient
{
    public function pricing(): array
    {
        $response = $this->request()->get('/pricing');

        return $this->decode($response);
    }

    /** @param array<string, mixed> $parameters */
    public function createGeneration(array $parameters, string $idempotencyKey): array
    {
        $response = $this->authenticatedRequest()
            ->withHeader('Idempotency-Key', $idempotencyKey)
            ->post('/generations', $parameters);

        return $this->decode($response);
    }

    public function generation(string $generationId): array
    {
        $response = $this->authenticatedRequest()->get('/generations/'.rawurlencode($generationId));

        return $this->decode($response);
    }

    /** @param array<string, int|string> $query */
    public function generations(array $query = []): array
    {
        $response = $this->authenticatedRequest()->get('/generations', $query);

        return $this->decode($response);
    }

    /** @param array{start_date?: string, end_date?: string} $query */
    public function usage(array $query = []): array
    {
        $response = $this->authenticatedRequest()->get('/usage', $query);

        return $this->decode($response);
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl((string) config('services.pixelforge.base_url'))
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('services.pixelforge.timeout', 30))
            ->connectTimeout((int) config('services.pixelforge.connect_timeout', 10));
    }

    private function authenticatedRequest(): PendingRequest
    {
        $apiKey = config('services.pixelforge.api_key');

        if (! is_string($apiKey) || $apiKey === '') {
            throw new RuntimeException('Pixelforge API credentials are not configured.');
        }

        return $this->request()->withToken($apiKey);
    }

    /** @return array<string, mixed> */
    private function decode(Response $response): array
    {
        if ($response->failed()) {
            $payload = $response->json();
            $error = is_array($payload) ? ($payload['error'] ?? []) : [];
            $message = is_array($error) ? ($error['message'] ?? 'Pixelforge request failed.') : 'Pixelforge request failed.';

            throw new PixelforgeApiException(
                (string) $message,
                $response->status(),
                is_array($error) ? ($error['code'] ?? null) : null,
                $response->header('Retry-After'),
                is_array($error) ? ($error['param'] ?? null) : null,
                is_array($error) && is_array($error['errors'] ?? null) ? $error['errors'] : [],
            );
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new RuntimeException('Pixelforge returned an invalid JSON response.');
        }

        return $payload;
    }
}
