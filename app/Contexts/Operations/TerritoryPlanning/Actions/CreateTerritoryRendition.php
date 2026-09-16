<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Actions;

use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlanRevision;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryRendition;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryPlanningAuthorization;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryPlanWriteState;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CreateTerritoryRendition
{
    public const MAX_BYTES = 5_000_000;

    public function __construct(
        private TerritoryPlanWriteState $writeState,
        private TerritoryPlanningAuthorization $authorization,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{id:string,territory_plan_revision_id:string,scope:string,media_type:string,content_checksum:string,content_bytes:int,metadata:array<string,mixed>,created_at:?string}
     */
    public function handle(
        string $actorPlayerId,
        string $planId,
        string $revisionId,
        string $scope,
        string $mediaType,
        string $contentBase64,
        array $metadata,
    ): array {
        $bytes = base64_decode($contentBase64, true);
        if ($bytes === false || $bytes === '' || strlen($bytes) > self::MAX_BYTES) {
            throw ValidationException::withMessages(['content' => 'Rendition content must be valid base64 between 1 byte and 5 MB.']);
        }
        if (! in_array($scope, ['world', 'viewport', 'alliance', 'hive'], true)) {
            throw ValidationException::withMessages(['scope' => 'Rendition scope is unsupported.']);
        }
        if (! in_array($mediaType, ['image/png', 'image/svg+xml', 'application/pdf'], true)) {
            throw ValidationException::withMessages(['media_type' => 'Rendition media type is unsupported.']);
        }
        $this->assertMediaMatches($mediaType, $bytes);
        $this->assertMetadata($metadata);
        $checksum = hash('sha256', $bytes);

        return DB::transaction(function () use ($actorPlayerId, $planId, $revisionId, $scope, $mediaType, $contentBase64, $metadata, $bytes, $checksum): array {
            $context = $this->writeState->lock($actorPlayerId, $planId);
            $this->authorization->authorizeManage($context);
            $revision = TerritoryPlanRevision::query()
                ->whereKey($revisionId)
                ->where('territory_plan_id', $planId)
                ->firstOrFail();
            if ($revision->map_dataset_id !== $context->plan->map_dataset_id
                || ! hash_equals($revision->map_dataset_checksum, $context->plan->map_dataset_checksum)) {
                throw ValidationException::withMessages(['revision' => 'Renditions must use the plan revision map pin.']);
            }

            $rendition = TerritoryRendition::query()->firstOrCreate(
                [
                    'territory_plan_revision_id' => $revisionId,
                    'scope' => $scope,
                    'media_type' => $mediaType,
                    'content_checksum' => $checksum,
                ],
                [
                    'territory_plan_id' => $planId,
                    'content_bytes' => strlen($bytes),
                    'content_base64' => $contentBase64,
                    'metadata' => $metadata,
                    'created_by_player_id' => $actorPlayerId,
                    'created_at' => now(),
                ],
            );

            return [
                'id' => (string) $rendition->id,
                'territory_plan_revision_id' => $revisionId,
                'scope' => $scope,
                'media_type' => $mediaType,
                'content_checksum' => $checksum,
                'content_bytes' => strlen($bytes),
                'metadata' => $this->metadata($rendition->getAttribute('metadata')),
                'created_at' => $this->date($rendition->getAttribute('created_at')),
            ];
        });
    }

    private function assertMediaMatches(string $mediaType, string $bytes): void
    {
        $valid = match ($mediaType) {
            'image/png' => str_starts_with($bytes, "\x89PNG\r\n\x1a\n"),
            'application/pdf' => str_starts_with($bytes, '%PDF-'),
            'image/svg+xml' => $this->safeSvg($bytes),
            default => false,
        };
        if (! $valid) {
            throw ValidationException::withMessages(['content' => 'Rendition bytes do not match the declared media type.']);
        }
    }

    private function safeSvg(string $bytes): bool
    {
        $trimmed = ltrim($bytes);
        if (! str_starts_with($trimmed, '<svg') && ! str_starts_with($trimmed, '<?xml')) {
            return false;
        }
        $lower = strtolower($bytes);

        return ! str_contains($lower, '<script')
            && ! preg_match('/\son[a-z]+\s*=/i', $bytes)
            && ! str_contains($lower, 'javascript:')
            && ! str_contains($lower, '<foreignobject');
    }

    /** @param  array<string, mixed>  $metadata */
    private function assertMetadata(array $metadata): void
    {
        $allowed = [
            'locale', 'font_family', 'artwork_manifest_version', 'artwork_registry_checksum',
            'map_dataset_id', 'map_dataset_checksum', 'width', 'height', 'exported_at',
        ];
        if (array_diff(array_keys($metadata), $allowed) !== []) {
            throw ValidationException::withMessages(['metadata' => 'Rendition metadata contains unsupported fields.']);
        }
        if (strlen(json_encode($metadata, JSON_THROW_ON_ERROR)) > 20_000) {
            throw ValidationException::withMessages(['metadata' => 'Rendition metadata is too large.']);
        }
    }

    /** @return array<string, mixed> */
    private function metadata(mixed $value): array
    {
        if (! is_array($value) || ($value !== [] && array_is_list($value))) {
            throw new \LogicException('Persisted Territory rendition metadata is invalid.');
        }

        $metadata = [];
        foreach ($value as $key => $item) {
            if (! is_string($key)) {
                throw new \LogicException('Persisted Territory rendition metadata is invalid.');
            }
            $metadata[$key] = $item;
        }

        return $metadata;
    }

    private function date(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }
        if (is_string($value) && $value !== '') {
            return $value;
        }

        return null;
    }
}
