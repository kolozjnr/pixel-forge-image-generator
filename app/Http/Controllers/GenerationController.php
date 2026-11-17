<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGenerationRequest;
use App\Models\Generation;
use App\Models\BillingLedgerEntry;
use App\Services\Pixelforge\GenerationSubmissionService;
use App\Services\Pixelforge\PixelforgeApiException;
use App\Services\Pixelforge\PixelforgePricingService;
use App\Services\Billing\CreditBalanceService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class GenerationController extends Controller
{
    public function dashboard(): InertiaResponse
    {
        $user = request()->user();

        return Inertia::render('Dashboard', [
            'stats' => [
                'image_count' => Generation::query()->whereBelongsTo($user)->where('status', 'succeeded')->count(),
                'balance_nano_usd' => max(0, $user->credit_balance_nano_usd - $user->reserved_credit_nano_usd),
                'month_spend_nano_usd' => BillingLedgerEntry::query()->whereBelongsTo($user)->where('entry_type', 'generation_charge')->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->sum('amount_nano_usd'),
            ],
            'recent' => Generation::query()->whereBelongsTo($user)->latest()->limit(6)->get()->map(fn (Generation $generation): array => [
                'id' => $generation->id,
                'status' => $generation->status,
                'prompt' => $generation->prompt,
                'image_url' => $generation->image_path ? route('generations.image', $generation) : null,
                'created_at' => $generation->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function create(PixelforgePricingService $pricing): InertiaResponse
    {
        try {
            $pricingData = $pricing->current();
        } catch (\Throwable) {
            $pricingData = null;
        }

        return Inertia::render('Generations/Create', [
            'pricing' => $pricingData,
        ]);
    }

    public function estimate(Request $request, PixelforgePricingService $pricing): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:4000'],
            'size' => ['required', 'in:1024x1024,1536x1024,1024x1536'],
            'quality' => ['required', 'in:low,medium,high'],
            'reference_count' => ['required', 'integer', 'min:0', 'max:4'],
        ]);

        return response()->json($pricing->estimate(
            $pricing->current(),
            $validated['prompt'],
            $validated['size'],
            $validated['quality'],
            $validated['reference_count'],
        ));
    }

    public function store(StoreGenerationRequest $request, GenerationSubmissionService $submission): RedirectResponse
    {
        try {
            $generation = $submission->submit($request->user(), $request->validated());
        } catch (PixelforgeApiException $exception) {
            return back()->withErrors([
                'prompt' => $exception->getMessage(),
            ])->withInput();
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['prompt' => $exception->getMessage()])->withInput();
        }

        return to_route('generations.show', $generation);
    }

    public function index(): InertiaResponse
    {
        $filters = request()->validate(['status' => ['nullable', 'in:queued,processing,succeeded,failed'], 'search' => ['nullable', 'string', 'max:200']]);

        return Inertia::render('Generations/Index', [
            'filters' => ['status' => $filters['status'] ?? '', 'search' => $filters['search'] ?? ''],
            'generations' => Generation::query()
                ->whereBelongsTo(request()->user())
                ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
                ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where('prompt', 'like', '%'.$search.'%'))
                ->latest()
                ->paginate(24)
                ->through(fn (Generation $generation): array => [
                    'id' => $generation->id,
                    'status' => $generation->status,
                    'prompt' => $generation->prompt,
                    'size' => $generation->size,
                    'quality' => $generation->quality,
                    'image_url' => $generation->image_path ? route('generations.image', $generation) : null,
                    'created_at' => $generation->created_at?->toIso8601String(),
                ]),
        ]);
    }

    public function show(Generation $generation): InertiaResponse
    {
        abort_unless($generation->user_id === request()->user()->id, 404);

        return Inertia::render('Generations/Show', [
            'generation' => [
                'id' => $generation->id,
                'status' => $generation->status,
                'prompt' => $generation->prompt,
                'size' => $generation->size,
                'quality' => $generation->quality,
                'image_url' => $generation->image_path ? route('generations.image', $generation) : null,
                'provider_error' => $generation->provider_error,
                'customer_cost_nano_usd' => $generation->customer_cost_nano_usd,
                'updated_at' => $generation->updated_at?->toIso8601String(),
            ],
        ]);
    }

    public function image(Generation $generation): Response
    {
        abort_unless($generation->user_id === request()->user()->id, 404);
        abort_unless($generation->image_path, 404);

        $disk = Storage::disk('s3')->exists($generation->image_path) ? 's3' : 'local';
        abort_unless(Storage::disk($disk)->exists($generation->image_path), 404);

        return response(Storage::disk($disk)->get($generation->image_path), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function refresh(Generation $generation, \App\Services\Pixelforge\PixelforgeClient $client, \App\Services\Pixelforge\GenerationLifecycleService $lifecycle): RedirectResponse
    {
        abort_unless($generation->user_id === request()->user()->id, 404);

        if ($generation->provider_id && ! in_array($generation->status, ['succeeded', 'failed'], true)) {
            try {
                $lifecycle->synchronize($client->generation($generation->provider_id));
            } catch (\Throwable) {
                return back()->withErrors(['generation' => 'Unable to refresh this generation right now.']);
            }
        }

        return back();
    }
}
