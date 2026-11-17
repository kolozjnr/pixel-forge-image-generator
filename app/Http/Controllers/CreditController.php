<?php

namespace App\Http\Controllers;

use App\Models\BillingLedgerEntry;
use App\Services\Billing\CreditBalanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class CreditController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $user = $request->user();

        return Inertia::render('Credits/Index', [
            'balance_nano_usd' => $user->credit_balance_nano_usd,
            'reserved_nano_usd' => $user->reserved_credit_nano_usd,
            'entries' => BillingLedgerEntry::query()->whereBelongsTo($user)->latest()->paginate(20)->through(fn (BillingLedgerEntry $entry): array => [
                'id' => $entry->id,
                'entry_type' => $entry->entry_type,
                'amount_nano_usd' => $entry->amount_nano_usd,
                'created_at' => $entry->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function purchase(Request $request, CreditBalanceService $credits): RedirectResponse
    {
        $data = $request->validate(['amount_usd' => ['required', 'in:5,10,25,50']]);
        $credits->purchaseSimulation($request->user(), (int) $data['amount_usd'] * 1_000_000_000);

        return back()->with('success', 'Simulated credits added to your account.');
    }
}
