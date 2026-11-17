<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PixelforgeWebhookController;
use App\Http\Controllers\GenerationController;
use App\Http\Controllers\CreditController;
use App\Http\Controllers\Admin\ReportController;

Route::post('/webhooks/pixelforge', PixelforgeWebhookController::class)->name('webhooks.pixelforge');

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [GenerationController::class, 'dashboard'])->name('dashboard');
    Route::get('/generations/create', [GenerationController::class, 'create'])->name('generations.create');
    Route::get('/generations/estimate', [GenerationController::class, 'estimate'])->name('generations.estimate');
    Route::post('/generations', [GenerationController::class, 'store'])->name('generations.store');
    Route::get('/generations', [GenerationController::class, 'index'])->name('generations.index');
    Route::get('/generations/{generation}', [GenerationController::class, 'show'])->name('generations.show');
    Route::get('/generations/{generation}/image', [GenerationController::class, 'image'])->name('generations.image');
    Route::post('/generations/{generation}/refresh', [GenerationController::class, 'refresh'])->name('generations.refresh');
    Route::get('/credits', [CreditController::class, 'index'])->name('credits.index');
    Route::post('/credits/simulate-purchase', [CreditController::class, 'purchase'])->name('credits.purchase');
    Route::middleware('admin')->get('/admin/reports', ReportController::class)->name('admin.reports');
});

require __DIR__.'/settings.php';
