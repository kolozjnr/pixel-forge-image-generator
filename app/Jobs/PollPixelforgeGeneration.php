<?php

namespace App\Jobs;

use App\Models\Generation;
use App\Services\Pixelforge\GenerationLifecycleService;
use App\Services\Pixelforge\PixelforgeClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class PollPixelforgeGeneration implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 60];

    public function __construct(public readonly int $generationId) {}

    public function handle(PixelforgeClient $client, GenerationLifecycleService $lifecycle): void
    {
        $generation = Generation::query()->find($this->generationId);

        if (! $generation || in_array($generation->status, ['succeeded', 'failed'], true)) {
            return;
        }

        if (! $generation->provider_id) {
            if ($this->attempts() >= $this->tries) {
                return;
            }

            $this->release(15);

            return;
        }

        try {
            $providerGeneration = $client->generation($generation->provider_id);
        } catch (Throwable $exception) {
            if ($this->attempts() >= $this->tries) {
                throw $exception;
            }

            $this->release(10);

            return;
        }

        $lifecycle->synchronize($providerGeneration);
    }
}
