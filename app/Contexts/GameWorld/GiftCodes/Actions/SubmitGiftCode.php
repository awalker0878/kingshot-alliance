<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Actions;

use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeSource;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeStatus;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCode;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class SubmitGiftCode
{
    public function __construct(
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /**
     * @param array{code: string, source_type?: string, source_label?: string|null, source_url?: string|null, expires_at?: string|null} $attributes
     */
    public function handle(PlayerReference $actor, array $attributes): GiftCode
    {
        return DB::transaction(function () use ($actor, $attributes): GiftCode {
            $code = trim($attributes['code']);
            if (! preg_match('/^[A-Za-z0-9_-]{3,64}$/', $code)) {
                throw ValidationException::withMessages([
                    'code' => 'Use 3–64 letters, numbers, dashes, or underscores.',
                ]);
            }

            $normalized = Str::upper($code);
            $source = GiftCodeSource::tryFrom($attributes['source_type'] ?? GiftCodeSource::Manual->value)
                ?? GiftCodeSource::Manual;
            $expiresAt = $this->date($attributes['expires_at'] ?? null);

            $giftCode = GiftCode::query()->firstOrNew(['normalized_code' => $normalized]);
            $created = ! $giftCode->exists;

            if ($created) {
                $giftCode->fill([
                    'code' => $code,
                    'normalized_code' => $normalized,
                    'created_by_player_id' => $actor->playerId,
                    'status' => GiftCodeStatus::Active,
                    'discovered_at' => now(),
                ]);
            }

            $giftCode->fill([
                'source_type' => $source,
                'source_label' => $this->optional($attributes['source_label'] ?? null),
                'source_url' => $this->optional($attributes['source_url'] ?? null),
                'expires_at' => $expiresAt,
            ]);
            $giftCode->save();

            $metadata = [
                'gift_code_id' => (string) $giftCode->id,
                'normalized_code' => $normalized,
                'source_type' => $source->value,
                'created' => $created,
            ];
            $this->audit->record('game_world.gift_code_submitted', $actor, $giftCode, null, $metadata);
            $this->outbox->record(
                'game_world.gift_code_submitted',
                null,
                $giftCode,
                $metadata,
                null,
                'player:'.$actor->playerId,
            );

            return $giftCode;
        });
    }

    private function optional(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }

    private function date(?string $value): ?Carbon
    {
        $value = $this->optional($value);

        return $value === null ? null : Carbon::parse($value)->endOfDay();
    }
}
