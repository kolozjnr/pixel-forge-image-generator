<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['event_id', 'event_type', 'generation_id', 'payload', 'received_at'])]
class PixelforgeWebhookEvent extends Model
{
    protected function casts(): array
    {
        return ['payload' => 'array', 'received_at' => 'datetime'];
    }
}
