<?php

namespace App\Console\Commands;

use App\Models\ProviderUsageReconciliation;
use App\Services\Pixelforge\PixelforgeClient;
use Illuminate\Console\Command;

class ReconcilePixelforgeUsage extends Command
{
    protected $signature = 'pixelforge:reconcile-usage {--date= : UTC date in YYYY-MM-DD format}';

    protected $description = 'Reconcile daily provider costs against Pixelforge usage';

    public function handle(PixelforgeClient $client): int
    {
        $date = $this->option('date') ?: now('UTC')->subDay()->toDateString();
        $payload = $client->usage(['start_date' => $date, 'end_date' => $date]);

        foreach ($payload['data'] ?? [] as $day) {
            if (! is_array($day) || ! is_string($day['date'] ?? null)) {
                continue;
            }

            ProviderUsageReconciliation::query()->updateOrCreate(
                ['usage_date' => $day['date']],
                [
                    'generation_count' => $day['generations'] ?? 0,
                    'provider_cost_nano_usd' => $this->nanoUsd((string) ($day['cost_usd'] ?? '0')),
                    'input_text_tokens' => $day['input_text_tokens'] ?? 0,
                    'input_image_tokens' => $day['input_image_tokens'] ?? 0,
                    'output_tokens' => $day['output_tokens'] ?? 0,
                    'reasoning_tokens' => $day['reasoning_tokens'] ?? 0,
                    'provider_payload' => $day,
                    'reconciled_at' => now(),
                ],
            );
        }

        $this->info('Pixelforge usage reconciled for '.$date.'.');

        return self::SUCCESS;
    }

    private function nanoUsd(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return ((int) $whole * 1_000_000_000) + (int) str_pad(substr($fraction, 0, 9), 9, '0');
    }
}
