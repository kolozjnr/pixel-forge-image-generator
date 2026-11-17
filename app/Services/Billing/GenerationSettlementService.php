<?php

namespace App\Services\Billing;

use App\Models\Generation;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class GenerationSettlementService
{
    public function __construct(private readonly CreditBalanceService $credits) {}

    public function settleSucceededGeneration(Generation $generation): Generation
    {
        return DB::transaction(function () use ($generation): Generation {
            $lockedGeneration = Generation::query()->lockForUpdate()->findOrFail($generation->id);

            if ($lockedGeneration->status !== 'succeeded' || $lockedGeneration->provider_cost_nano_usd !== null) {
                return $lockedGeneration;
            }

            $usage = [
                'input_text_tokens' => $lockedGeneration->input_text_tokens,
                'input_image_tokens' => $lockedGeneration->input_image_tokens,
                'output_tokens' => $lockedGeneration->output_tokens,
                'reasoning_tokens' => $lockedGeneration->reasoning_tokens,
            ];

            foreach ($usage as $tokenCount) {
                if (! is_int($tokenCount) || $tokenCount < 0) {
                    throw new InvalidArgumentException('Successful generation is missing valid token usage.');
                }
            }

            $rates = $lockedGeneration->rates;

            if (! is_array($rates)) {
                throw new InvalidArgumentException('Generation is missing its creation-time provider rates.');
            }

            $providerCost = 0;

            foreach ($usage as $rateKey => $tokenCount) {
                $providerCost += $this->tokenCostNanoUsd($tokenCount, $rateKey, $rates);
            }

            $customerCost = intdiv($providerCost * 5, 4);

            $lockedGeneration->provider_cost_nano_usd = $providerCost;
            $lockedGeneration->customer_cost_nano_usd = $customerCost;
            $lockedGeneration->save();
            $this->credits->settleReservation($lockedGeneration, $customerCost);

            return $lockedGeneration;
        });
    }

    /** @param array<string, mixed> $rates */
    private function tokenCostNanoUsd(int $tokens, string $rateKey, array $rates): int
    {
        $rate = $rates[$rateKey] ?? null;

        if (! is_string($rate) || ! preg_match('/^\d+(?:\.\d{1,9})?$/', $rate)) {
            throw new InvalidArgumentException('Generation contains an invalid creation-time rate.');
        }

        [$whole, $fraction] = array_pad(explode('.', $rate, 2), 2, '');
        $nanoPerMillion = ((int) $whole * 1_000_000_000) + (int) str_pad($fraction, 9, '0');

        return intdiv($tokens * $nanoPerMillion, 1_000_000);
    }
}
