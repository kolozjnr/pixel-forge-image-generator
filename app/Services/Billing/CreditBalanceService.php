<?php

namespace App\Services\Billing;

use App\Models\BillingLedgerEntry;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class CreditBalanceService
{
    public function purchaseSimulation(User $user, int $amountNanoUsd): User
    {
        if (! in_array($amountNanoUsd, [5, 10, 25, 50], true) && ! in_array($amountNanoUsd, [5_000_000_000, 10_000_000_000, 25_000_000_000, 50_000_000_000], true)) {
            throw new RuntimeException('Unsupported simulated credit amount.');
        }
        return DB::transaction(function () use ($user, $amountNanoUsd): User {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            if ($lockedUser->credit_balance_nano_usd > PHP_INT_MAX - $amountNanoUsd) {
                throw new RuntimeException('Credit balance limit exceeded.');
            }
            $lockedUser->credit_balance_nano_usd += $amountNanoUsd;
            $lockedUser->save();
            BillingLedgerEntry::query()->create(['user_id' => $lockedUser->id, 'entry_type' => 'simulated_purchase', 'amount_nano_usd' => $amountNanoUsd, 'currency' => 'USD', 'idempotency_key' => 'purchase:'.Str::uuid(), 'metadata' => ['simulated' => true]]);

            return $lockedUser;
        });
    }

    public function reserve(User $user, Generation $generation, int $amountNanoUsd): void
    {
        DB::transaction(function () use ($user, $generation, $amountNanoUsd): void {
            if (BillingLedgerEntry::query()->where('idempotency_key', 'generation:'.$generation->id.':reservation')->exists()) {
                return;
            }
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            if (($lockedUser->credit_balance_nano_usd - $lockedUser->reserved_credit_nano_usd) < $amountNanoUsd) {
                throw new RuntimeException('Your available balance is too low for this estimate.');
            }
            $lockedUser->reserved_credit_nano_usd += $amountNanoUsd;
            $lockedUser->save();
            BillingLedgerEntry::query()->create(['user_id' => $lockedUser->id, 'generation_id' => $generation->id, 'entry_type' => 'generation_reservation', 'amount_nano_usd' => $amountNanoUsd, 'currency' => 'USD', 'idempotency_key' => 'generation:'.$generation->id.':reservation', 'metadata' => []]);
        });
    }

    public function release(Generation $generation): void
    {
        DB::transaction(function () use ($generation): void {
            $reservation = BillingLedgerEntry::query()->where('generation_id', $generation->id)->where('entry_type', 'generation_reservation')->first();
            if (! $reservation || BillingLedgerEntry::query()->where('idempotency_key', 'generation:'.$generation->id.':release')->exists()) {
                return;
            }
            $lockedUser = User::query()->lockForUpdate()->findOrFail($reservation->user_id);
            $this->releaseLockedReservation($lockedUser, $generation, $reservation);
        });
    }

    public function settleReservation(Generation $generation, int $chargeNanoUsd): void
    {
        DB::transaction(function () use ($generation, $chargeNanoUsd): void {
            $reservation = BillingLedgerEntry::query()->where('generation_id', $generation->id)->where('entry_type', 'generation_reservation')->first();
            if (BillingLedgerEntry::query()->where('idempotency_key', 'generation:'.$generation->id.':settled')->exists()) {
                return;
            }

            if (! $reservation) {
                $lockedUser = User::query()->lockForUpdate()->findOrFail($generation->user_id);
                if ($lockedUser->credit_balance_nano_usd - $lockedUser->reserved_credit_nano_usd < $chargeNanoUsd) {
                    throw new RuntimeException('Actual generation charge exceeds available credits.');
                }
                $lockedUser->credit_balance_nano_usd -= $chargeNanoUsd;
                $lockedUser->save();
                $this->recordSettlement($lockedUser, $generation, $chargeNanoUsd);

                return;
            }
            $lockedUser = User::query()->lockForUpdate()->findOrFail($reservation->user_id);
            if ($lockedUser->credit_balance_nano_usd < $chargeNanoUsd) {
                throw new RuntimeException('Actual generation charge exceeds purchased credits.');
            }
            $lockedUser->credit_balance_nano_usd -= $chargeNanoUsd;
            $this->releaseLockedReservation($lockedUser, $generation, $reservation);
            $this->recordSettlement($lockedUser, $generation, $chargeNanoUsd);
        });
    }

    private function releaseLockedReservation(User $user, Generation $generation, BillingLedgerEntry $reservation): void
    {
        $user->reserved_credit_nano_usd -= $reservation->amount_nano_usd;
        $user->save();
        if (! BillingLedgerEntry::query()->where('idempotency_key', 'generation:'.$generation->id.':release')->exists()) {
            BillingLedgerEntry::query()->create(['user_id' => $user->id, 'generation_id' => $generation->id, 'entry_type' => 'generation_release', 'amount_nano_usd' => $reservation->amount_nano_usd, 'currency' => 'USD', 'idempotency_key' => 'generation:'.$generation->id.':release', 'metadata' => []]);
        }
    }

    private function recordSettlement(User $user, Generation $generation, int $chargeNanoUsd): void
    {
        BillingLedgerEntry::query()->create(['user_id' => $user->id, 'generation_id' => $generation->id, 'entry_type' => 'generation_settlement', 'amount_nano_usd' => $chargeNanoUsd, 'currency' => 'USD', 'idempotency_key' => 'generation:'.$generation->id.':settled', 'metadata' => []]);
        BillingLedgerEntry::query()->create(['user_id' => $user->id, 'generation_id' => $generation->id, 'entry_type' => 'generation_charge', 'amount_nano_usd' => $chargeNanoUsd, 'currency' => 'USD', 'idempotency_key' => 'generation:'.$generation->id.':charge', 'metadata' => ['markup_percent' => 25]]);
    }
}
