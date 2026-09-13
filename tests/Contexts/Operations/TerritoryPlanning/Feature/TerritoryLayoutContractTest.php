<?php

declare(strict_types=1);

namespace Tests\Contexts\Operations\TerritoryPlanning\Feature;

use App\Contexts\Operations\TerritoryPlanning\Actions\ImportTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Actions\SaveTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryLayoutContract;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryPlanSnapshotBuilder;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class TerritoryLayoutContractTest extends TestCase
{
    public function test_import_rejects_changed_preview_content_before_attempting_persistence(): void
    {
        $document = json_encode($this->document(), JSON_THROW_ON_ERROR);
        $checksum = hash('sha256', $document);
        $this->expectException(ValidationException::class);
        app(ImportTerritoryPlan::class)->handle('unresolved-actor', 'unresolved-plan', 1, $document.' ', $checksum);
    }

    public function test_owner_save_rejects_non_integer_coordinates_before_attempting_persistence(): void
    {
        foreach (['100', 100.5, true, null, [], 1000001] as $invalid) {
            $object = $this->object();
            $object['x'] = $invalid;
            try {
                app(SaveTerritoryPlan::class)->handle('unresolved-actor', 'unresolved-plan', 1, $this->alliances(), [], [$object]);
                self::fail('A malformed coordinate reached persistence.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('objects', $exception->errors());
            }
        }
    }

    public function test_rejects_unsupported_metadata_preferences_and_loose_booleans(): void
    {
        $contract = app(TerritoryLayoutContract::class);
        $cases = [
            [$this->alliances(), [array_replace($this->object(), ['metadata' => ['script' => '<script>']])], []],
            [$this->alliances(), [$this->object()], ['invented_game_rule' => true]],
            [$this->alliances(), [$this->object()], ['march_seconds_per_tile' => '2']],
            [$this->alliances(), [$this->object()], ['march_seconds_per_tile' => INF]],
            [[array_replace($this->alliances()[0], ['visible' => 'false'])], [$this->object()], []],
            [$this->alliances(), [array_replace($this->object(), ['rotation' => 90.0])], []],
        ];
        foreach ($cases as [$alliances, $objects, $preferences]) {
            try {
                $contract->normalize($alliances, [], $objects, $preferences);
                self::fail('Malformed layout was accepted.');
            } catch (ValidationException $exception) {
                self::assertNotEmpty($exception->errors());
            }
        }
    }

    public function test_schema_two_requires_exact_map_pin_and_rejects_old_or_coerced_versions(): void
    {
        $contract = app(TerritoryLayoutContract::class);
        $document = $this->document();
        foreach ([1, '2', 3, null] as $version) {
            $document['schema_version'] = $version;
            try {
                $contract->decode(json_encode($document, JSON_THROW_ON_ERROR));
                self::fail('Unsupported document schema accepted.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('import', $exception->errors());
            }
        }
        $document = $this->document();
        unset($document['plan']['map_dataset_checksum']);
        $this->expectException(ValidationException::class);
        $contract->decode(json_encode($document, JSON_THROW_ON_ERROR));
    }

    public function test_save_and_document_share_normalization_and_preserve_known_slot_metadata(): void
    {
        $contract = app(TerritoryLayoutContract::class);
        $document = $this->document();
        $document['objects'][0]['metadata'] = ['locked' => true, 'slot_state' => 'reserved', 'external_identity_key' => 'governor-1'];
        $direct = $contract->normalize($document['alliances'], [], $document['objects']);
        $decoded = $contract->decode(json_encode($document, JSON_THROW_ON_ERROR));
        self::assertSame($direct['objects'], $decoded['objects']);
        self::assertSame($direct['alliances'], $decoded['alliances']);
        self::assertSame('governor-1', $decoded['objects'][0]['metadata']['external_identity_key']);
    }

    public function test_open_slots_cannot_hide_assigned_identity(): void
    {
        $object = $this->object();
        $object['metadata'] = ['slot_state' => 'open'];
        $object['external_player_name'] = 'Reserved Governor';
        $this->expectException(ValidationException::class);
        app(TerritoryLayoutContract::class)->normalize($this->alliances(), [], [$object]);
    }

    public function test_json_depth_and_bytes_are_bounded_at_the_owner_boundary(): void
    {
        foreach ([str_repeat(' ', TerritoryLayoutContract::MAX_DOCUMENT_BYTES + 1), str_repeat('[', 40).'0'.str_repeat(']', 40)] as $json) {
            try {
                app(TerritoryLayoutContract::class)->decode($json);
                self::fail('An unbounded document was accepted.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('import', $exception->errors());
            }
        }
    }

    public function test_snapshot_hash_is_stable_after_json_object_key_reordering_but_detects_layout_change(): void
    {
        $snapshots = app(TerritoryPlanSnapshotBuilder::class);
        $document = $this->document();
        $reordered = array_reverse($document, true);
        $reordered['plan'] = array_reverse($document['plan'], true);
        self::assertSame($snapshots->checksum($document), $snapshots->checksum($reordered));
        $reordered['objects'][0]['x']++;
        self::assertNotSame($snapshots->checksum($document), $snapshots->checksum($reordered));
    }

    /** @return list<array<string, mixed>> */
    private function alliances(): array
    {
        return [['key' => 'external', 'external_name' => 'External Alliance', 'display_name' => 'External Alliance']];
    }

    /** @return array<string, mixed> */
    private function object(): array
    {
        return ['key' => 'city', 'alliance_key' => 'external', 'type' => 'governor_city', 'x' => 100, 'y' => 100];
    }

    /** @return array<string, mixed> */
    private function document(): array
    {
        return ['schema_version' => 2, 'plan' => ['map_dataset_id' => 'test-map', 'map_dataset_checksum' => str_repeat('a', 64)],
            'alliances' => $this->alliances(), 'groups' => [], 'objects' => [$this->object()]];
    }
}
