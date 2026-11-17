<?php

namespace App\Services\Pixelforge;

use App\Services\Billing\CreditBalanceService;
use App\Models\Generation;
use Illuminate\Support\Facades\DB;

class GenerationLifecycleService
{
    /** @param array<string, mixed> $providerGeneration */
    public function synchronize(array $providerGeneration): ?Generation
    {
        $providerId = $providerGeneration['id'] ?? null;
        $status = $providerGeneration['status'] ?? null;

        if (! is_string($providerId) || ! in_array($status, ['queued', 'processing', 'succeeded', 'failed'], true)) {
            return null;
        }

        $synced = DB::transaction(function () use ($providerGeneration, $providerId, $status): ?Generation {
            $metadata = is_array($providerGeneration['metadata'] ?? null) ? $providerGeneration['metadata'] : [];
            $localId = $metadata['local_generation_id'] ?? null;
            $generation = Generation::query()
                ->where('provider_id', $providerId)
                ->when(is_string($localId), fn ($query) => $query->orWhereKey($localId))
                ->lockForUpdate()
                ->first();

            if (! $generation) {
                return null;
            }

            $generation->provider_id ??= $providerId;

            if ($generation->provider_id !== $providerId) {
                return null;
            }

            if (in_array($generation->status, ['succeeded', 'failed'], true)) {
                return $generation;
            }

            if ($generation->status === 'processing' && $status === 'queued') {
                return $generation;
            }

            $generation->status = $status;
            $generation->started_at = $providerGeneration['started_at'] ?? $generation->started_at;
            $generation->completed_at = $providerGeneration['completed_at'] ?? $generation->completed_at;
            $generation->provider_image_url = $providerGeneration['image_url'] ?? null;
            $generation->image_url_expires_at = $providerGeneration['image_url_expires_at'] ?? null;
            $generation->rates = is_array($providerGeneration['rates'] ?? null) ? $providerGeneration['rates'] : $generation->rates;
            $generation->provider_error = is_array($providerGeneration['error'] ?? null) ? $providerGeneration['error'] : null;

            $usage = $providerGeneration['usage'] ?? null;

            if (is_array($usage)) {
                $generation->input_text_tokens = $usage['input_text_tokens'] ?? null;
                $generation->input_image_tokens = $usage['input_image_tokens'] ?? null;
                $generation->output_tokens = $usage['output_tokens'] ?? null;
                $generation->reasoning_tokens = $usage['reasoning_tokens'] ?? null;
                $generation->total_tokens = $usage['total_tokens'] ?? null;
            }

            $generation->save();

            return $generation;
        });

        if ($synced && $synced->status === 'failed') {
            app(CreditBalanceService::class)->release($synced);
        }

        return $synced;
    }

    /** @param array<string, mixed> $event */
    public function recordWebhook(array $event): bool
    {
        $eventId = $event['id'] ?? null;
        $eventType = $event['type'] ?? null;
        $generation = $event['data']['object'] ?? null;

        if (! is_string($eventId) || ! is_string($eventType) || ! is_array($generation)) {
            return false;
        }

        $created = DB::table('pixelforge_webhook_events')->insertOrIgnore([
            'event_id' => $eventId,
            'event_type' => $eventType,
            'generation_id' => is_string($generation['id'] ?? null) ? $generation['id'] : null,
            'payload' => json_encode($event, JSON_THROW_ON_ERROR),
            'received_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($created === 0) {
            return false;
        }

        $this->synchronize($generation);

        return true;
    }

}
