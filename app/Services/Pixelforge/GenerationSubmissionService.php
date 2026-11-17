<?php

namespace App\Services\Pixelforge;

use App\Jobs\PollPixelforgeGeneration;
use App\Models\Generation;
use App\Models\User;
use App\Services\Billing\CreditBalanceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerationSubmissionService
{
    public function __construct(private readonly PixelforgeClient $client, private readonly PixelforgePricingService $pricing, private readonly CreditBalanceService $credits) {}

    /** @param array{prompt: string, size: string, quality: string, reference_images?: array<int, string>} $input */
    public function submit(User $user, array $input): Generation
    {
        $idempotencyKey = (string) Str::uuid();
        $estimate = $this->pricing->estimate($this->pricing->current(), $input['prompt'], $input['size'], $input['quality'], count($input['reference_images'] ?? []));

        if (($user->credit_balance_nano_usd - $user->reserved_credit_nano_usd) < $estimate['customer_max_nano_usd']) {
            throw new \RuntimeException('Add credits before generating this image.');
        }

        $generation = DB::transaction(fn (): Generation => Generation::query()->create([
            'user_id' => $user->id,
            'idempotency_key' => $idempotencyKey,
            'status' => 'queued',
            'prompt' => $input['prompt'],
            'size' => $input['size'],
            'quality' => $input['quality'],
            'reference_images' => $input['reference_images'] ?? [],
            'metadata' => [],
        ]));

        $this->credits->reserve($user, $generation, $estimate['customer_max_nano_usd']);

        try {
            $providerGeneration = $this->client->createGeneration([
                'prompt' => $input['prompt'],
                'size' => $input['size'],
                'quality' => $input['quality'],
                'reference_images' => $input['reference_images'] ?? [],
                'metadata' => ['local_generation_id' => (string) $generation->id],
            ], $idempotencyKey);
        } catch (PixelforgeApiException $exception) {
            if (in_array($exception->errorCode, ['overloaded', 'rate_limit_exceeded', 'concurrency_limit_exceeded'], true)) {
                Log::notice('Pixelforge declined generation submission.', [
                    'generation_id' => $generation->id,
                    'provider_code' => $exception->errorCode,
                    'retry_after' => $exception->retryAfter,
                ]);
                $this->credits->release($generation);

                throw $exception;
            }

            $generation->update([
                'status' => 'failed',
                'provider_error' => ['code' => $exception->errorCode, 'message' => $exception->getMessage()],
                'completed_at' => now(),
            ]);
            $this->credits->release($generation);

            throw $exception;
        } catch (Throwable $exception) {
            Log::warning('Pixelforge generation request outcome is uncertain.', [
                'generation_id' => $generation->id,
                'exception' => $exception::class,
            ]);

            PollPixelforgeGeneration::dispatch($generation->id)->delay(now()->addSeconds(15));

            return $generation;
        }

        $synchronizedGeneration = app(GenerationLifecycleService::class)->synchronize($providerGeneration);

        if (! $synchronizedGeneration) {
            $generation->update([
                'provider_id' => $providerGeneration['id'] ?? null,
                'rates' => $providerGeneration['rates'] ?? null,
                'status' => $providerGeneration['status'] ?? 'queued',
            ]);
            $synchronizedGeneration = $generation->fresh();
        }

        if ($synchronizedGeneration->status === 'succeeded') {
            \App\Jobs\SettleSucceededGeneration::dispatch($synchronizedGeneration->id);
            \App\Jobs\PersistGenerationImage::dispatch($synchronizedGeneration->id);
        }

        if ($synchronizedGeneration->status === 'failed') {
            $this->credits->release($synchronizedGeneration);
        }

        if ($synchronizedGeneration && ! in_array($synchronizedGeneration->status, ['succeeded', 'failed'], true)) {
            PollPixelforgeGeneration::dispatch($synchronizedGeneration->id)->delay(now()->addSeconds(30));
        }

        return $synchronizedGeneration ?? $generation;
    }
}
