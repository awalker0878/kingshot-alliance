<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Progression\Http\Controllers;

use App\Contexts\GameWorld\Players\Services\PlayerContext;
use App\Contexts\GameWorld\Progression\Queries\ProgressionDatasetQuery;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ProgressionLibraryController extends Controller
{
    public function __invoke(Request $request, PlayerContext $context, ProgressionDatasetQuery $datasets): Response
    {
        $context->player();
        $dataset = $datasets->latest();
        $query = mb_strtolower(trim((string) $request->query('q', '')));
        $generation = $request->integer('generation');
        $troopClass = trim((string) $request->query('troop_class', ''));

        $heroes = array_values(array_filter(
            $dataset->heroes,
            static function (array $hero) use ($query, $generation, $troopClass): bool {
                if ($query !== '' && ! str_contains(mb_strtolower((string) ($hero['name'] ?? '')), $query)) {
                    return false;
                }
                if ($generation > 0 && (int) ($hero['generation'] ?? 0) !== $generation) {
                    return false;
                }
                if ($troopClass !== '' && ($hero['troop_class'] ?? null) !== $troopClass) {
                    return false;
                }

                return true;
            },
        ));

        return Inertia::render('Kingdom/Progression/Index', [
            'dataset' => [
                'id' => $dataset->id,
                'version' => $dataset->datasetVersion,
                'observed_at' => $dataset->observedAt,
                'checksum' => $dataset->checksum,
                'review_status' => $dataset->release['review_status'] ?? 'unknown',
            ],
            'filters' => ['q' => (string) $request->query('q', ''), 'generation' => $generation > 0 ? $generation : null, 'troop_class' => $troopClass !== '' ? $troopClass : null],
            'heroes' => $heroes,
            'formations' => $dataset->formations,
            'systems' => $dataset->systems,
            'sources' => $dataset->sources(),
            'conflicts' => $dataset->conflicts(),
            'dispositions' => $dataset->dispositions(),
        ]);
    }
}
