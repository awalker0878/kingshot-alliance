<?php

declare(strict_types=1);

namespace Tests\Contexts\Operations\TerritoryPlanning\Feature;

use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryLayoutDocumentContract;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class TerritoryLayoutDocumentContractTest extends TestCase
{
    public function test_annotations_round_trip_through_the_interchange_envelope(): void
    {
        $document = $this->document();
        $document['annotations'] = [[
            'key' => 'attack-lane',
            'kind' => 'arrow',
            'alliance_key' => 'external',
            'text' => 'Attack lane',
            'x' => 100,
            'y' => 200,
            'target_x' => 140,
            'target_y' => 240,
            'sort_order' => 0,
        ]];

        $decoded = app(TerritoryLayoutDocumentContract::class)->decode(
            json_encode($document, JSON_THROW_ON_ERROR),
        );

        self::assertSame($document['annotations'], $decoded['annotations']);
        self::assertSame('external', $decoded['alliances'][0]['key']);
    }

    public function test_omitted_annotations_normalize_to_an_empty_interchange_collection(): void
    {
        $decoded = app(TerritoryLayoutDocumentContract::class)->decode(
            json_encode($this->document(), JSON_THROW_ON_ERROR),
        );

        self::assertSame([], $decoded['annotations']);
    }

    public function test_annotation_alliance_references_must_exist_in_the_layout(): void
    {
        $document = $this->document();
        $document['annotations'] = [[
            'key' => 'bad-layer',
            'kind' => 'label',
            'alliance_key' => 'missing',
            'text' => 'Invalid',
            'x' => 100,
            'y' => 200,
            'target_x' => null,
            'target_y' => null,
            'sort_order' => 0,
        ]];

        $this->expectException(ValidationException::class);
        app(TerritoryLayoutDocumentContract::class)->decode(
            json_encode($document, JSON_THROW_ON_ERROR),
        );
    }

    public function test_annotation_envelope_keeps_unknown_top_level_fields_strict(): void
    {
        $document = $this->document();
        $document['script'] = '<script>alert(1)</script>';

        $this->expectException(ValidationException::class);
        app(TerritoryLayoutDocumentContract::class)->decode(
            json_encode($document, JSON_THROW_ON_ERROR),
        );
    }

    /** @return array<string, mixed> */
    private function document(): array
    {
        return [
            'schema_version' => 2,
            'plan' => [
                'map_dataset_id' => 'test-map',
                'map_dataset_checksum' => str_repeat('a', 64),
            ],
            'alliances' => [[
                'key' => 'external',
                'external_name' => 'External Alliance',
                'display_name' => 'External Alliance',
            ]],
            'groups' => [],
            'objects' => [[
                'key' => 'city',
                'alliance_key' => 'external',
                'type' => 'governor_city',
                'x' => 100,
                'y' => 100,
            ]],
        ];
    }
}
