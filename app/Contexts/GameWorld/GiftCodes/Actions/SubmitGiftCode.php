<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Actions;

use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeSource;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeStatus;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCode;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeProvenance;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeReference;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeSubmissionResult;
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
        private ReconcileGiftCodeStatus $reconcile,
    ) {}

    /**
     * @param  array{code: string, source_type?: string, source_label?: string|null, source_url?: string|null, expires_at?: string|null}  $attributes
     */
    public function handle(PlayerReference $actor, array $attributes): GiftCodeSubmissionResult
    {
        return DB::transaction(function () use ($actor, $attributes): GiftCodeSubmissionResult {
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

            $sourceLabel = $this->optional($attributes['source_label'] ?? null);
            $sourceUrl = $this->optional($attributes['source_url'] ?? null);

            if ($created) {
                $giftCode->fill([
                    'code' => $code,
                    'normalized_code' => $normalized,
                    'created_by_player_id' => $actor->playerId,
                    'status' => GiftCodeStatus::Pending,
                    'status_changed_at' => now(),
                    'discovered_at' => now(),
                    'source_type' => $source,
                    'source_label' => $sourceLabel,
                    'source_url' => $sourceUrl,
                    'expires_at' => $expiresAt,
                ]);
                $giftCode->save();
            } elseif ($expiresAt !== null
                && ($giftCode->expires_at === null || $expiresAt->isBefore($giftCode->expires_at))) {
                $giftCode->forceFill(['expires_at' => $expiresAt])->save();
            }

            $fingerprint = hash('sha256', implode('|', [
                $actor->playerId,
                $source->value,
                mb_strtolower($sourceLabel ?? ''),
                mb_strtolower($sourceUrl ?? ''),
            ]));
            $provenance = GiftCodeProvenance::query()->firstOrCreate([
                'gift_code_id' => (string) $giftCode->id,
                'fingerprint' => $fingerprint,
            ], [
                'submitted_by_player_id' => $actor->playerId,
                'source_type' => $source,
                'source_label' => $sourceLabel,
                'source_url' => $sourceUrl,
                'observed_at' => now(),
            ]);

            $metadata = [
                'gift_code_id' => (string) $giftCode->id,
                'code' => (string) $giftCode->code,
                'normalized_code' => $normalized,
                'status' => $giftCode->status->value,
                'source_type' => $source->value,
                'expires_at' => $giftCode->expires_at?->toIso8601String(),
                'created' => $created,
                'duplicate_detected' => ! $created,
                'provenance_added' => $provenance->wasRecentlyCreated,
                'provenance_id' => (string) $provenance->id,
            ];
            $this->audit->record('game_world.gift_code_submitted', $actor, $giftCode, null, $metadata);
            $this->outbox->record(
                $created ? 'gift_code.created' : 'gift_code.provenance_added',
                null,
                $giftCode,
                $metadata,
                null,
                'player:'.$actor->playerId,
            );
            $this->reconcile->handle((string) $giftCode->id, $actor);

            return new GiftCodeSubmissionResult(
                new GiftCodeReference(
                    (string) $giftCode->id,
                    $giftCode->code,
                    $giftCode->status,
                    $giftCode->expires_at,
                ),
                ! $created,
                $provenance->wasRecentlyCreated,
            );
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
