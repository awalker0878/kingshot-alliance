<?php

declare(strict_types=1);

namespace App\ReadModels\RecruitmentDiscovery\Queries;

use App\Contexts\Alliance\Lifecycle\Enums\AllianceStatus;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentApplicationMode;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class PublicRecruitmentBoardQuery
{
    private const RESULT_LIMIT = 100;

    /**
     * @return array{
     *   alliances: list<array{name:string, slug:string, title:string, introduction:string|null, kingdom:int, language:string, timezone:string, profileUrl:string, applicationUrl:string}>,
     *   filters: array{q:string, kingdom:int|null, language:string},
     *   facets: array{kingdoms:list<int>, languages:list<string>},
     *   resultLimitReached: bool
     * }
     */
    public function search(?string $search, ?int $kingdomNumber, ?string $language): array
    {
        $search = trim((string) $search);
        $language = strtolower(trim((string) $language));
        $base = $this->listedAlliances();

        $kingdoms = (clone $base)
            ->distinct()
            ->orderBy('kingdoms.number')
            ->pluck('kingdoms.number')
            ->map(static fn (mixed $number): int => (int) $number)
            ->values()
            ->all();
        $languages = (clone $base)
            ->distinct()
            ->orderBy('alliances.language')
            ->pluck('alliances.language')
            ->map(static fn (mixed $value): string => (string) $value)
            ->values()
            ->all();

        if ($search !== '') {
            $like = '%'.mb_strtolower($search).'%';
            $base->where(static function (Builder $query) use ($like): void {
                $query
                    ->whereRaw('LOWER(alliances.name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(recruitment_settings.title) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(recruitment_settings.introduction) LIKE ?', [$like]);
            });
        }

        if ($kingdomNumber !== null) {
            $base->where('kingdoms.number', $kingdomNumber);
        }

        if ($language !== '') {
            $base->whereRaw('LOWER(alliances.language) = ?', [$language]);
        }

        $rows = $base
            ->select([
                'alliances.name',
                'alliances.slug',
                'alliances.language',
                'alliances.timezone',
                'recruitment_settings.title',
                'recruitment_settings.introduction',
                'kingdoms.number as kingdom_number',
            ])
            ->orderBy('kingdoms.number')
            ->orderBy('alliances.name')
            ->limit(self::RESULT_LIMIT + 1)
            ->get();

        $resultLimitReached = $rows->count() > self::RESULT_LIMIT;

        $alliances = [];
        foreach ($rows->take(self::RESULT_LIMIT) as $row) {
            $slug = (string) $row->slug;
            $introduction = $row->introduction;

            $alliances[] = [
                'name' => (string) $row->name,
                'slug' => $slug,
                'title' => (string) $row->title,
                'introduction' => is_string($introduction) && trim($introduction) !== ''
                    ? $introduction
                    : null,
                'kingdom' => (int) $row->kingdom_number,
                'language' => (string) $row->language,
                'timezone' => (string) $row->timezone,
                'profileUrl' => route('public.alliances.show', $slug),
                'applicationUrl' => route('public.alliances.recruitment.show', [
                    'slug' => $slug,
                    'source' => 'recruitment-board',
                ]),
            ];
        }

        return [
            'alliances' => $alliances,
            'filters' => [
                'q' => $search,
                'kingdom' => $kingdomNumber,
                'language' => $language,
            ],
            'facets' => [
                'kingdoms' => $kingdoms,
                'languages' => $languages,
            ],
            'resultLimitReached' => $resultLimitReached,
        ];
    }

    private function listedAlliances(): Builder
    {
        return DB::table('recruitment_settings')
            ->join('alliances', 'alliances.id', '=', 'recruitment_settings.alliance_id')
            ->join('kingdoms', 'kingdoms.id', '=', 'alliances.kingdom_id')
            ->where('recruitment_settings.is_listed', true)
            ->where('recruitment_settings.is_open', true)
            ->where('recruitment_settings.application_mode', RecruitmentApplicationMode::Public->value)
            ->where('alliances.status', AllianceStatus::Active->value);
    }
}
