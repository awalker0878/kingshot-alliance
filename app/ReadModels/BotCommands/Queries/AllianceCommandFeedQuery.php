<?php

declare(strict_types=1);

namespace App\ReadModels\BotCommands\Queries;

use App\Contexts\Alliance\Content\Enums\ContentStatus;
use App\Contexts\Alliance\Content\Enums\ContentType;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Recruitment\Queries\PublicRecruitmentQuery;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeStatus;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use App\Contexts\Operations\Events\Enums\EventOccurrenceStatus;
use App\Contexts\Operations\Events\Enums\EventScope;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class AllianceCommandFeedQuery
{
    public function __construct(
        private AllianceReferenceQuery $alliances,
        private KingdomReferenceQuery $kingdoms,
        private PublicRecruitmentQuery $recruitment,
    ) {}

    /** @return array<string, mixed> */
    public function overview(string $allianceId): array
    {
        $alliance = $this->alliances->require($allianceId);
        $kingdom = $this->kingdoms->find($alliance->kingdomId);

        return [
            'alliance' => [
                'id' => $alliance->allianceId,
                'name' => $alliance->name,
                'slug' => $alliance->slug,
                'kingdom' => $kingdom?->number,
                'language' => $alliance->language,
                'timezone' => $alliance->timezone,
            ],
            'events' => $this->events($allianceId, 10),
            'gift_codes' => $this->giftCodes(10),
            'knowledge' => $this->knowledge($allianceId, null, null, 10),
            'recruitment' => $this->recruitment->forAlliance(
                $allianceId,
                $alliance->slug,
                'bot-command',
            ),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function events(string $allianceId, int $limit): array
    {
        return array_values(DB::table('event_occurrences')
            ->join('events', 'events.id', '=', 'event_occurrences.event_id')
            ->where('events.scope', EventScope::Alliance->value)
            ->where('events.alliance_id', $allianceId)
            ->where('event_occurrences.status', EventOccurrenceStatus::Scheduled->value)
            ->where('event_occurrences.starts_at', '>=', now())
            ->orderBy('event_occurrences.starts_at')
            ->limit($limit)
            ->get([
                'event_occurrences.id',
                'event_occurrences.starts_at',
                'event_occurrences.ends_at',
                'events.title',
                'events.timezone',
            ])
            ->map(static fn (object $row): array => [
                'id' => (string) $row->id,
                'title' => (string) $row->title,
                'starts_at' => (string) $row->starts_at,
                'ends_at' => $row->ends_at === null ? null : (string) $row->ends_at,
                'timezone' => (string) $row->timezone,
            ])
            ->values()
            ->all());
    }

    /** @return list<array<string, mixed>> */
    public function giftCodes(int $limit): array
    {
        $redemptionUrl = config('game_world.gift_code_redemption_url');
        $redemptionUrl = is_string($redemptionUrl) ? $redemptionUrl : null;

        return array_values(DB::table('gift_codes')
            ->where('status', GiftCodeStatus::Valid->value)
            ->where(static function (Builder $query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderByRaw('expires_at IS NULL')
            ->orderBy('expires_at')
            ->orderByDesc('discovered_at')
            ->limit($limit)
            ->get([
                'id',
                'code',
                'status',
                'status_reason_code',
                'status_revision',
                'discovered_at',
                'expires_at',
                'expires_precision',
                'expires_revision',
            ])
            ->map(static fn (object $row): array => [
                'id' => (string) $row->id,
                'code' => (string) $row->code,
                'trust_status' => (string) $row->status,
                'reason_code' => $row->status_reason_code === null ? null : (string) $row->status_reason_code,
                'status_revision' => (int) $row->status_revision,
                'discovered_at' => (string) $row->discovered_at,
                'expires_at' => $row->expires_at === null ? null : (string) $row->expires_at,
                'expires_precision' => $row->expires_precision === null ? null : (string) $row->expires_precision,
                'expires_revision' => (int) $row->expires_revision,
                'official_redemption_url' => $redemptionUrl,
            ])
            ->values()
            ->all());
    }

    /** @return list<array<string, mixed>> */
    public function knowledge(
        string $allianceId,
        ?string $search,
        ?ContentType $type,
        int $limit,
    ): array {
        $query = DB::table('content_items')
            ->where('alliance_id', $allianceId)
            ->where('status', ContentStatus::Published->value)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->whereNull('archived_at');

        $search = trim((string) $search);
        if ($search !== '') {
            $like = '%'.mb_strtolower($search).'%';
            $query->where(static function (Builder $query) use ($like): void {
                $query
                    ->whereRaw('LOWER(title) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(summary) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(body) LIKE ?', [$like]);
            });
        }

        if ($type instanceof ContentType) {
            $query->where('type', $type->value);
        }

        return array_values($query
            ->orderByDesc('published_at')
            ->orderBy('sort_order')
            ->limit($limit)
            ->get([
                'id',
                'type',
                'visibility',
                'title',
                'slug',
                'summary',
                'body',
                'locale',
                'source_label',
                'source_url',
                'game_version',
                'reviewed_at',
                'published_at',
            ])
            ->map(static fn (object $row): array => [
                'id' => (string) $row->id,
                'type' => (string) $row->type,
                'visibility' => (string) $row->visibility,
                'title' => (string) $row->title,
                'slug' => (string) $row->slug,
                'summary' => $row->summary === null ? null : (string) $row->summary,
                'excerpt' => Str::limit(strip_tags((string) $row->body), 1000),
                'locale' => (string) $row->locale,
                'source_label' => $row->source_label === null ? null : (string) $row->source_label,
                'source_url' => $row->source_url === null ? null : (string) $row->source_url,
                'game_version' => $row->game_version === null ? null : (string) $row->game_version,
                'reviewed_at' => $row->reviewed_at === null ? null : (string) $row->reviewed_at,
                'published_at' => (string) $row->published_at,
            ])
            ->values()
            ->all());
    }
}
