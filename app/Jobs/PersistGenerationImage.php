<?php

namespace App\Jobs;

use App\Models\Generation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class PersistGenerationImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 8;

    /** @var array<int, int> */
    public array $backoff = [30, 60, 120, 300];

    public function __construct(public readonly int $generationId) {}

    public function handle(): void
    {
        $generation = Generation::query()->find($this->generationId);

        if (! $generation || $generation->status !== 'succeeded' || ! $generation->provider_image_url || $generation->image_path) {
            return;
        }

        $providerHost = parse_url((string) config('services.pixelforge.base_url'), PHP_URL_HOST);
        $imageHost = parse_url($generation->provider_image_url, PHP_URL_HOST);

        if (! is_string($providerHost) || $imageHost !== $providerHost || parse_url($generation->provider_image_url, PHP_URL_SCHEME) !== 'https') {
            return;
        }

        $response = Http::timeout(60)->get($generation->provider_image_url);

        if (! $response->successful() || ! str_starts_with((string) $response->header('Content-Type'), 'image/png')) {
            $this->release(30);

            return;
        }

        $path = 'generations/'.$generation->user_id.'/'.$generation->id.'.png';

        $disk = config('filesystems.disks.s3.bucket') ? 's3' : 'local';

        if (Storage::disk($disk)->put($path, $response->body(), ['visibility' => 'private', 'ContentType' => 'image/png'])) {
            $generation->update(['image_path' => $path]);
        }
    }
}
