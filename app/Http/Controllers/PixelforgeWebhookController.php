<?php

namespace App\Http\Controllers;

use App\Services\Pixelforge\GenerationLifecycleService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PixelforgeWebhookController extends Controller
{
    public function __invoke(Request $request, GenerationLifecycleService $lifecycle): Response
    {
        $secret = config('services.pixelforge.webhook_secret');

        if (! is_string($secret) || $secret === '') {
            abort(503, 'Pixelforge webhook signing secret is not configured.');
        }

        $rawPayload = $request->getContent();
        $parts = [];

        foreach (explode(',', (string) $request->header('Pixelforge-Signature')) as $part) {
            [$key, $value] = array_pad(explode('=', $part, 2), 2, '');
            $parts[$key] = $value;
        }

        $timestamp = filter_var($parts['t'] ?? null, FILTER_VALIDATE_INT);
        $signature = $parts['v1'] ?? '';

        if ($timestamp === false || ! is_string($signature) || abs(time() - $timestamp) > 300) {
            abort(400, 'Invalid Pixelforge signature.');
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$rawPayload, $secret);

        if (! hash_equals($expected, $signature)) {
            abort(400, 'Invalid Pixelforge signature.');
        }

        $event = json_decode($rawPayload, true);

        if (! is_array($event)) {
            abort(400, 'Invalid Pixelforge event payload.');
        }

        if (($event['type'] ?? null) === 'ping') {
            return response()->noContent();
        }

        if (! in_array($event['type'] ?? null, ['generation.processing', 'generation.succeeded', 'generation.failed'], true)) {
            abort(400, 'Unsupported Pixelforge event type.');
        }

        if (! $lifecycle->recordWebhook($event)) {
            $eventId = $event['id'] ?? null;
            $exists = is_string($eventId) && \Illuminate\Support\Facades\DB::table('pixelforge_webhook_events')->where('event_id', $eventId)->exists();

            if (! $exists) {
                abort(400, 'Invalid Pixelforge event payload.');
            }
        }

        return response()->noContent();
    }
}
