<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BillingLedgerEntry;
use App\Models\Generation;
use App\Models\User;
use App\Services\Pixelforge\PixelforgeClient;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function __invoke(Request $request, PixelforgeClient $client): Response
    {
        $dateRange = $request->validate([
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);
        $from = $dateRange['start_date'] ?? now()->subDays(29)->toDateString();
        $to = $dateRange['end_date'] ?? now()->toDateString();
        abort_if(\Illuminate\Support\Carbon::parse($from)->diffInDays(\Illuminate\Support\Carbon::parse($to)) > 91, 422, 'Choose a date range of 92 days or fewer.');
        $providerUsage = null;

        try {
            $providerUsage = $client->usage(['start_date' => $from, 'end_date' => $to]);
        } catch (\Throwable) {
            $providerUsage = ['error' => 'Pixelforge usage report is currently unavailable.'];
        }

        return Inertia::render('Admin/Reports', [
            'provider_usage' => $providerUsage,
            'daily_charges' => BillingLedgerEntry::query()->selectRaw('DATE(created_at) as date, SUM(amount_nano_usd) as amount_nano_usd')->where('entry_type', 'generation_charge')->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])->groupByRaw('DATE(created_at)')->orderBy('date')->get(),
            'users' => User::query()->withSum(['billingLedgerEntries as spend_nano_usd' => fn ($query) => $query->where('entry_type', 'generation_charge')], 'amount_nano_usd')->orderByDesc('spend_nano_usd')->limit(100)->get(['id', 'name', 'email']),
            'generation_totals' => Generation::query()->selectRaw('status, COUNT(*) as count')->groupBy('status')->get(),
        ]);
    }
}
