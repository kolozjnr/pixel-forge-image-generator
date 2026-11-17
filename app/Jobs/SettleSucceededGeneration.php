<?php

namespace App\Jobs;

use App\Services\Billing\GenerationSettlementService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SettleSucceededGeneration implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 10;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 60, 120, 300];

    public function __construct(public readonly int $generationId) {}

    public function handle(GenerationSettlementService $settlement): void
    {
        $generation = \App\Models\Generation::query()->find($this->generationId);

        if ($generation) {
            $settlement->settleSucceededGeneration($generation);
        }
    }
}
