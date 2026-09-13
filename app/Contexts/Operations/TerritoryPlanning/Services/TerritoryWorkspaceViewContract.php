<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Services;

use App\Contexts\GameWorld\KingdomMaps\ValueObjects\KingdomMapDataset;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class TerritoryWorkspaceViewContract
{
    private const LAYERS = 'terrain,zones,restrictions,structures,facilities,resources,coverage,planned,observed,comparison,annotations';

    /** @param array<mixed> $views
     * @return list<array<string, mixed>>
     */
    public function validate(array $views, KingdomMapDataset $dataset): array
    {
        if (! array_is_list($views)) {
            throw ValidationException::withMessages(['views' => 'Saved views must be a list.']);
        }
        $bounds = $dataset->data['bounds'];
        Validator::make(['views' => $views], [
            'views' => ['present', 'array', 'max:20'],
            'views.*' => ['array:key,name,center_x,center_y,zoom,layers,show_grid,show_labels'],
            'views.*.key' => ['required', 'string', 'regex:/^[a-zA-Z0-9_-]{1,80}$/', 'distinct:strict'],
            'views.*.name' => ['required', 'string', 'max:80'],
            'views.*.center_x' => ['required', 'numeric', 'between:'.$bounds['x'].','.($bounds['x'] + $bounds['width'])],
            'views.*.center_y' => ['required', 'numeric', 'between:'.$bounds['y'].','.($bounds['y'] + $bounds['height'])],
            'views.*.zoom' => ['required', 'numeric', 'between:0.1,100'],
            'views.*.layers' => ['present', 'array:'.self::LAYERS],
            'views.*.layers.*' => ['array:visible,opacity'],
            'views.*.layers.*.visible' => ['required', 'boolean:strict'],
            'views.*.layers.*.opacity' => ['required', 'numeric', 'between:0,1'],
            'views.*.show_grid' => ['required', 'boolean:strict'],
            'views.*.show_labels' => ['required', 'boolean:strict'],
        ])->validate();

        $normalized = [];
        foreach ($views as $view) {
            $normalized[] = [
                'key' => (string) $view['key'],
                'name' => trim((string) $view['name']),
                'center_x' => (float) $view['center_x'],
                'center_y' => (float) $view['center_y'],
                'zoom' => (float) $view['zoom'],
                'layers' => $view['layers'],
                'show_grid' => $view['show_grid'],
                'show_labels' => $view['show_labels'],
            ];
        }

        return $normalized;
    }
}
