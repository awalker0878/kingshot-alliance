<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class MetaWebhookAuthenticator
{
    public function assertValid(Request $request, GiftCodeSourceRegistry $source, string $body): void
    {
        $secret = trim((string) config('game_world.gift_codes.meta_app_secret', ''));
        if (strlen($secret) < 32) {
            abort(503, 'Meta webhook verification is not configured.');
        }

        $signature = trim((string) $request->header('X-Hub-Signature-256', ''));
        if (preg_match('/^sha256=([a-f0-9]{64})$/D', $signature, $matches) !== 1) {
            $source->increment('signature_failure_count');
            throw ValidationException::withMessages(['signature' => 'The Meta webhook signature is missing or invalid.']);
        }

        $expected = hash_hmac('sha256', $body, $secret);
        if (! hash_equals($expected, $matches[1])) {
            $source->increment('signature_failure_count');
            throw ValidationException::withMessages(['signature' => 'The Meta webhook signature is invalid.']);
        }
    }
}
