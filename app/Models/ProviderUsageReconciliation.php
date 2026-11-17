<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['usage_date', 'generation_count', 'provider_cost_nano_usd', 'input_text_tokens', 'input_image_tokens', 'output_tokens', 'reasoning_tokens', 'provider_payload', 'reconciled_at'])]
class ProviderUsageReconciliation extends Model
{
    protected function casts(): array
    {
        return ['usage_date' => 'date', 'provider_payload' => 'array', 'reconciled_at' => 'datetime'];
    }
}
