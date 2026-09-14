<?php

declare(strict_types=1);

namespace Tests\Contexts\Operations\TerritoryPlanning\Unit;

use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryCoordinateTableAdapter;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class TerritoryCoordinateTableAdapterTest extends TestCase
{
    public function test_it_round_trips_supported_coordinate_semantics(): void
    {
        $adapter = new TerritoryCoordinateTableAdapter;
        $objects = [[
            'key' => 'city-1',
            'type' => 'governor_city',
            'x' => 120,
            'y' => 340,
            'rotation' => 90,
            'alliance_key' => 'north',
            'group_key' => 'hive-a',
            'player_id' => null,
            'external_player_name' => 'North Star',
            'label' => 'Front slot',
            'metadata' => [
                'variant_key' => 'default',
                'slot_state' => 'assigned',
                'external_identity_key' => 'external-city-1',
            ],
        ]];

        $csv = $adapter->encode($objects);
        $decoded = $adapter->decode($csv);

        self::assertCount(1, $decoded);
        self::assertSame('city-1', $decoded[0]['key']);
        self::assertSame('governor_city', $decoded[0]['type']);
        self::assertSame(120, $decoded[0]['x']);
        self::assertSame(340, $decoded[0]['y']);
        self::assertSame(90, $decoded[0]['rotation']);
        self::assertSame('assigned', $decoded[0]['metadata']['slot_state']);
        self::assertSame('external-city-1', $decoded[0]['metadata']['external_identity_key']);
    }

    public function test_it_rejects_unknown_headers_types_fractional_coordinates_and_invalid_slot_semantics(): void
    {
        $adapter = new TerritoryCoordinateTableAdapter;
        $header = implode(',', TerritoryCoordinateTableAdapter::HEADER);

        foreach ([
            "key,type,x\nfoo,banner,1\n",
            $header."\nfoo,unknown,,1,2,0,,,,,,,\n",
            $header."\nfoo,banner,,1.5,2,0,,,,,,,\n",
            $header."\nfoo,banner,,1,2,0,,,,,,assigned,\n",
        ] as $invalid) {
            try {
                $adapter->decode($invalid);
                self::fail('Expected invalid coordinate CSV to be rejected.');
            } catch (ValidationException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function test_it_neutralizes_spreadsheet_formula_prefixes_on_export(): void
    {
        $adapter = new TerritoryCoordinateTableAdapter;
        $csv = $adapter->encode([[
            'key' => '=HYPERLINK("https://example.invalid")',
            'type' => 'banner',
            'x' => 1,
            'y' => 2,
            'rotation' => 0,
            'alliance_key' => '+SUM(1,1)',
            'group_key' => null,
            'player_id' => null,
            'external_player_name' => null,
            'label' => '@danger',
            'metadata' => ['variant_key' => 'default'],
        ]]);

        self::assertStringContainsString("'=HYPERLINK", $csv);
        self::assertStringContainsString("'+SUM(1,1)", $csv);
        self::assertStringContainsString("'@danger", $csv);
    }

    public function test_it_enforces_input_byte_limit(): void
    {
        $this->expectException(ValidationException::class);
        (new TerritoryCoordinateTableAdapter)->decode(str_repeat('x', TerritoryCoordinateTableAdapter::MAX_BYTES + 1));
    }
}
