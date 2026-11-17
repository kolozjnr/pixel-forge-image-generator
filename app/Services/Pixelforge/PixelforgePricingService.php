<?php

namespace App\Services\Pixelforge;

use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

class PixelforgePricingService
{
    public function __construct(private readonly PixelforgeClient $client) {}

    /** @return array<string, mixed> */
    public function current(bool $refresh = false): array
    {
        if ($refresh) {
            Cache::forget('pixelforge.pricing');
        }

        return Cache::remember('pixelforge.pricing', now()->addMinutes(15), fn (): array => $this->client->pricing());
    }

    /** @param array<string, mixed> $pricing
     *  @return array{provider_min_nano_usd: int, provider_max_nano_usd: int, customer_min_nano_usd: int, customer_max_nano_usd: int, input_text_tokens_estimate: int, input_text_tokens_min: int, input_text_tokens_max: int, input_image_tokens: int, output_tokens: int, reasoning_tokens_min: int, reasoning_tokens_max: int}
     */
    public function estimate(array $pricing, string $prompt, string $size, string $quality, int $referenceCount): array
    {
        $tokens = $pricing['tokens'] ?? null;
        $rates = $pricing['rates'] ?? null;

        if (! is_array($tokens) || ! is_array($rates) || ! isset($tokens['output'][$quality][$size], $tokens['reasoning'][$quality], $tokens['characters_per_text_token'], $tokens['text_token_variance'], $tokens['per_reference_image'])) {
            throw new InvalidArgumentException('Pricing data is incomplete for the selected options.');
        }

        $characters = mb_strlen($prompt, 'UTF-8');
        $textEstimate = (int) ceil($characters / (int) $tokens['characters_per_text_token']);
        $variance = (int) round(((float) $tokens['text_token_variance']) * 100);
        $textMinimum = intdiv($textEstimate * (100 - $variance), 100);
        $textMaximum = intdiv(($textEstimate * (100 + $variance)) + 99, 100);
        $imageTokens = $referenceCount * (int) $tokens['per_reference_image'];
        $outputTokens = (int) $tokens['output'][$quality][$size];
        $reasoningMinimum = (int) $tokens['reasoning'][$quality]['min'];
        $reasoningMaximum = (int) $tokens['reasoning'][$quality]['max'];

        $providerMinimum = $this->cost($textMinimum, 'input_text_tokens', $rates)
            + $this->cost($imageTokens, 'input_image_tokens', $rates)
            + $this->cost($outputTokens, 'output_tokens', $rates)
            + $this->cost($reasoningMinimum, 'reasoning_tokens', $rates);
        $providerMaximum = $this->cost($textMaximum, 'input_text_tokens', $rates)
            + $this->cost($imageTokens, 'input_image_tokens', $rates)
            + $this->cost($outputTokens, 'output_tokens', $rates)
            + $this->cost($reasoningMaximum, 'reasoning_tokens', $rates);

        return [
            'provider_min_nano_usd' => $providerMinimum,
            'provider_max_nano_usd' => $providerMaximum,
            'customer_min_nano_usd' => intdiv($providerMinimum * 5, 4),
            'customer_max_nano_usd' => intdiv($providerMaximum * 5, 4),
            'input_text_tokens_estimate' => $textEstimate,
            'input_text_tokens_min' => $textMinimum,
            'input_text_tokens_max' => $textMaximum,
            'input_image_tokens' => $imageTokens,
            'output_tokens' => $outputTokens,
            'reasoning_tokens_min' => $reasoningMinimum,
            'reasoning_tokens_max' => $reasoningMaximum,
        ];
    }

    /** @param array<string, mixed> $rates */
    private function cost(int $tokenCount, string $rateKey, array $rates): int
    {
        $rate = $rates[$rateKey] ?? null;

        if (! is_string($rate) || ! preg_match('/^\d+(?:\.\d{1,9})?$/', $rate)) {
            throw new InvalidArgumentException('Pricing endpoint returned an invalid rate.');
        }

        [$whole, $fraction] = array_pad(explode('.', $rate, 2), 2, '');
        $rateInNanoUsdPerMillionTokens = ((int) $whole * 1_000_000_000) + (int) str_pad($fraction, 9, '0');
        $denominator = 1_000_000;
        $numerator = $tokenCount * $rateInNanoUsdPerMillionTokens;

        return intdiv($numerator, $denominator);
    }
}
