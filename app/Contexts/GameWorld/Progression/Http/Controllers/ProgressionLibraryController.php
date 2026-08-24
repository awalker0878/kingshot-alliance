<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Progression\Http\Controllers;

use App\Contexts\GameWorld\Players\Services\PlayerContext;
use App\Contexts\GameWorld\Progression\Queries\ProgressionDatasetQuery;
use App\Contexts\GameWorld\Progression\Queries\ProgressionFamilyQuery;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ProgressionLibraryController extends Controller
{
    public function __invoke(
        Request $request,
        PlayerContext $context,
        ProgressionDatasetQuery $datasets,
        ProgressionFamilyQuery $families,
    ): Response {
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

        $family = trim((string) $request->query('family', ''));
        $familyQuery = trim((string) $request->query('family_q', ''));
        $familyPage = max(1, $request->integer('family_page', 1));
        $familyData = $family === '' ? null : $families->page($dataset, $family, $familyQuery, $familyPage);
        $familyOptions = array_values(array_unique([
            'heroes',
            'hero_skills',
            'formations',
            ...$dataset->catalogueFamilies(),
        ]));
        sort($familyOptions);

        $user = $request->user();

        return Inertia::render('Kingdom/Progression/Library', [
            'user' => ['name' => (string) $user?->name, 'email' => (string) $user?->email],
            'dataset' => [
                'id' => $dataset->id,
                'version' => $dataset->datasetVersion,
                'schemaVersion' => $dataset->schemaVersion,
                'observed_at' => $dataset->observedAt,
                'checksum' => $dataset->checksum,
                'review_status' => $dataset->release['review_status'] ?? 'unknown',
            ],
            'filters' => [
                'q' => (string) $request->query('q', ''),
                'generation' => $generation > 0 ? $generation : null,
                'troop_class' => $troopClass !== '' ? $troopClass : null,
                'family' => $family !== '' ? $family : null,
                'family_q' => $familyQuery !== '' ? $familyQuery : null,
            ],
            'heroes' => $heroes,
            'formations' => $dataset->formations,
            'systems' => $dataset->systems,
            'sources' => $dataset->sources(),
            'conflicts' => $dataset->conflicts(),
            'sourceGaps' => $dataset->sourceGaps(),
            'dispositions' => $dataset->dispositions(),
            'familyOptions' => $familyOptions,
            'familyData' => $familyData,
        ]);
    }
}
