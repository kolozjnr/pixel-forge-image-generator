<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $provider_id
 * @property string $idempotency_key
 * @property string $status
 * @property string $prompt
 * @property string $size
 * @property string $quality
 * @property array<int, string> $reference_images
 * @property array<string, string> $metadata
 * @property string|null $image_path
 * @property string|null $provider_image_url
 * @property Carbon|null $image_url_expires_at
 * @property int|null $provider_cost_nano_usd
 * @property int|null $customer_cost_nano_usd
 */
#[Fillable(['user_id', 'provider_id', 'idempotency_key', 'status', 'prompt', 'size', 'quality', 'reference_images', 'metadata', 'image_path', 'provider_image_url', 'image_url_expires_at', 'input_text_tokens', 'input_image_tokens', 'output_tokens', 'reasoning_tokens', 'total_tokens', 'provider_cost_nano_usd', 'customer_cost_nano_usd', 'rates', 'provider_error', 'started_at', 'completed_at'])]
#[Hidden(['provider_image_url', 'idempotency_key'])]
class Generation extends Model
{
    protected function casts(): array
    {
        return [
            'reference_images' => 'array',
            'metadata' => 'array',
            'rates' => 'array',
            'provider_error' => 'array',
            'image_url_expires_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
