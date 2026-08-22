<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Services;

use Illuminate\Validation\ValidationException;

final class HiveLayoutGenerator
{
    /** @return list<array{key:string,type:string,x:int,y:int,alliance_key:string,group_key:string,label:?string}> */
    public function generate(string $style, string $allianceKey, int $centerX, int $centerY, int $cityCount = 50): array
    {
        if (! in_array($style, ['swirl', 'banner_pad'], true)) {
            throw ValidationException::withMessages(['style' => 'Hive style must be swirl or banner_pad.']);
        }
        if ($cityCount < 1 || $cityCount > 100) {
            throw ValidationException::withMessages(['city_count' => 'Hive city count must be between 1 and 100.']);
        }

        $group = 'hive-'.substr(hash('sha256', $style.'|'.$allianceKey.'|'.$centerX.'|'.$centerY.'|'.$cityCount), 0, 12);
        $objects = [[
            'key' => $group.'-trap', 'type' => 'bear_trap', 'x' => $centerX, 'y' => $centerY,
            'alliance_key' => $allianceKey, 'group_key' => $group, 'label' => 'Bear Trap',
        ]];

        $cityCoordinates = $style === 'swirl'
            ? $this->spiralCoordinates($centerX, $centerY, $cityCount)
            : $this->padCoordinates($centerX, $centerY, $cityCount);

        foreach ($cityCoordinates as $index => [$x, $y]) {
            $objects[] = [
                'key' => $group.'-city-'.($index + 1), 'type' => 'governor_city', 'x' => $x, 'y' => $y,
                'alliance_key' => $allianceKey, 'group_key' => $group, 'label' => 'Governor '.($index + 1),
            ];
        }

        $bannerTarget = $style === 'swirl' ? min(14, max(4, (int) ceil($cityCount / 8))) : min(18, max(5, (int) ceil($cityCount / 6)));
        $bannerRadius = $style === 'swirl' ? 9 : 8;
        for ($i = 0; $i < $bannerTarget; $i++) {
            $angle = (2 * M_PI * $i) / $bannerTarget;
            $objects[] = [
                'key' => $group.'-banner-'.($i + 1), 'type' => 'banner',
                'x' => (int) round($centerX + cos($angle) * $bannerRadius),
                'y' => (int) round($centerY + sin($angle) * $bannerRadius),
                'alliance_key' => $allianceKey, 'group_key' => $group, 'label' => 'Banner '.($i + 1),
            ];
        }

        return $objects;
    }

    /** @return list<array{0:int,1:int}> */
    private function spiralCoordinates(int $centerX, int $centerY, int $count): array
    {
        $result = [];
        $x = 0;
        $y = 0;
        $dx = 1;
        $dy = 0;
        $segmentLength = 1;
        $segmentPassed = 0;
        $turns = 0;

        while (count($result) < $count) {
            $x += $dx;
            $y += $dy;
            $segmentPassed++;
            if (max(abs($x), abs($y)) >= 3 && ($x % 3 === 0 || $y % 3 === 0)) {
                $result[] = [$centerX + ($x * 2), $centerY + ($y * 2)];
            }
            if ($segmentPassed >= $segmentLength) {
                $segmentPassed = 0;
                [$dx, $dy] = [-$dy, $dx];
                $turns++;
                if ($turns % 2 === 0) {
                    $segmentLength++;
                }
            }
        }

        return $result;
    }

    /** @return list<array{0:int,1:int}> */
    private function padCoordinates(int $centerX, int $centerY, int $count): array
    {
        $result = [];
        $columns = (int) ceil(sqrt($count));
        $rows = (int) ceil($count / $columns);
        $startX = $centerX - (($columns * 3) >> 1);
        $startY = $centerY - (($rows * 3) >> 1);
        for ($row = 0; $row < $rows && count($result) < $count; $row++) {
            for ($column = 0; $column < $columns && count($result) < $count; $column++) {
                $x = $startX + ($column * 3);
                $y = $startY + ($row * 3);
                if (abs($x - $centerX) <= 3 && abs($y - $centerY) <= 3) {
                    continue;
                }
                $result[] = [$x, $y];
            }
        }

        return $result;
    }
}
