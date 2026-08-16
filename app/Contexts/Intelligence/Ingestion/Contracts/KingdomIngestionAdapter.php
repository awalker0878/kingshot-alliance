<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Ingestion\Contracts;

use App\Contexts\Intelligence\Ingestion\Enums\KingdomIngestionTargetKind;

interface KingdomIngestionAdapter
{
    public function key(): string;

    public function version(): string;

    public function label(): string;

    /** @return list<KingdomIngestionTargetKind> */
    public function supportedTargetKinds(): array;

    /**
     * Normalize one already-acquired source record into the bounded K4
     * candidate envelope. Slice A deliberately defines no acquisition method.
     *
     * @param  array<string, mixed>  $record
     * @return array{
     *   target_kind: KingdomIngestionTargetKind|string,
     *   stable_game_id?: string|null,
     *   source_record_id?: string|null,
     *   captured_at: string,
     *   payload: array<string, mixed>
     * }
     */
    public function normalize(array $record): array;
}
