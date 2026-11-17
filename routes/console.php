<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function (): void {
    \App\Models\Generation::query()
        ->whereIn('status', ['queued', 'processing'])
        ->whereNotNull('provider_id')
        ->select('id')
        ->chunkById(100, function ($generations): void {
            foreach ($generations as $generation) {
                \App\Jobs\PollPixelforgeGeneration::dispatch($generation->id);
            }
        });
})->name('poll-pixelforge-generations')->everyFiveMinutes()->withoutOverlapping();

Schedule::command('pixelforge:reconcile-usage')->dailyAt('02:30')->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
