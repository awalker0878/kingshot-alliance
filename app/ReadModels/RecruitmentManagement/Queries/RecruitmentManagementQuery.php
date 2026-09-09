<?php

declare(strict_types=1);

namespace App\ReadModels\RecruitmentManagement\Queries;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCandidate;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentDecisionTemplate;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentOnboardingItem;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentQuestion;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentSetting;
use App\Contexts\Alliance\Recruitment\Queries\RecruitmentMetricsQuery;
use App\Shared\Infrastructure\Pagination\PageSlice;
use App\Shared\Infrastructure\Pagination\ScopedCursorCodec;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

final readonly class RecruitmentManagementQuery
{
    public const CANDIDATE_PAGE_SIZE = 50;

    public function __construct(
        private RecruitmentMetricsQuery $metrics,
        private AllianceAuthorization $authorization,
        private ScopedCursorCodec $cursors,
    ) {}

    /**
     * @param  array{q?: string|null, stage?: string|null, source?: string|null}  $filters
     * @param  array{questions?:string|null,templates?:string|null,onboarding?:string|null}  $catalogueCursors
     * @return array{
     *   settings: array{mode: string, title: string, introduction: string|null, retentionDays: int, open: bool, listed: bool}|null,
     *   questionPage: array{items: list<array{id: string, prompt: string, helpText: string|null, type: string, options: list<string>, required: bool, position: int, active: bool}>, nextCursor:string|null, hasMore:bool, pageSize:int, isFirstPage:bool},
     *   candidatePage: array{items: list<array{id: string, name: string, email: string, contactHandle: string|null, source: string|null, stage: string, submittedAt: string, firstRespondedAt: string|null, nextActionAt: string|null}>, nextCursor: string|null, hasMore: bool, pageSize: int, isFirstPage: bool},
     *   candidateFilters: array{q: string, stage: string, source: string},
     *   nextPositions: array{questions:int,onboarding:int},
     *   templatePage: array{items: list<array{id: string, name: string, decisionStage: string, subject: string, body: string, active: bool}>, nextCursor:string|null, hasMore:bool, pageSize:int, isFirstPage:bool},
     *   onboardingPage: array{items: list<array{id: string, name: string, description: string|null, position: int, required: bool, active: bool}>, nextCursor:string|null, hasMore:bool, pageSize:int, isFirstPage:bool},
     *   metrics: array<string, mixed>
     * }
     */
    public function forAlliance(
        string $actorPlayerId,
        string $allianceId,
        array $filters = [],
        ?string $cursor = null,
        array $catalogueCursors = [],
    ): array {
        $this->authorization->authorize($actorPlayerId, $allianceId, AlliancePermission::RecruitmentManage);
        $settings = RecruitmentSetting::query()->where('alliance_id', $allianceId)->first();
        $questions = $this->cataloguePage(RecruitmentQuestion::query()->where('alliance_id', $allianceId), 'recruitment-questions|'.$allianceId, 'position', $catalogueCursors['questions'] ?? null);
        $templates = $this->cataloguePage(RecruitmentDecisionTemplate::query()->where('alliance_id', $allianceId), 'recruitment-templates|'.$allianceId, 'name', $catalogueCursors['templates'] ?? null);
        $onboardingItems = $this->cataloguePage(RecruitmentOnboardingItem::query()->where('alliance_id', $allianceId), 'recruitment-onboarding|'.$allianceId, 'position', $catalogueCursors['onboarding'] ?? null);

        $normalizedFilters = $this->normalizeFilters($filters);

        return [
            'settings' => $settings instanceof RecruitmentSetting ? [
                'mode' => $settings->application_mode->value,
                'title' => (string) $settings->title,
                'introduction' => $settings->introduction,
                'retentionDays' => (int) $settings->retention_unsuccessful_days,
                'open' => (bool) $settings->is_open,
                'listed' => (bool) $settings->is_listed,
            ] : null,
            'questionPage' => (new PageSlice(array_map(static fn (RecruitmentQuestion $question): array => [
                'id' => (string) $question->id,
                'prompt' => (string) $question->prompt,
                'helpText' => $question->help_text,
                'type' => $question->type()->value,
                'options' => $question->optionValues(),
                'required' => (bool) $question->is_required,
                'position' => (int) $question->position,
                'active' => (bool) $question->is_active,
            ], $questions->items), $questions->nextCursor, $questions->pageSize, $questions->isFirstPage))->toArray(),
            'candidatePage' => $this->candidates($allianceId, $normalizedFilters, $cursor)->toArray(),
            'candidateFilters' => $normalizedFilters,
            'nextPositions' => [
                'questions' => min(65535, (int) RecruitmentQuestion::query()->where('alliance_id', $allianceId)->max('position') + 1),
                'onboarding' => min(65535, (int) RecruitmentOnboardingItem::query()->where('alliance_id', $allianceId)->max('position') + 1),
            ],
            'templatePage' => (new PageSlice(array_map(static fn (RecruitmentDecisionTemplate $template): array => [
                'id' => (string) $template->id,
                'name' => (string) $template->name,
                'decisionStage' => $template->decisionStage()->value,
                'subject' => (string) $template->subject,
                'body' => (string) $template->body,
                'active' => (bool) $template->is_active,
            ], $templates->items), $templates->nextCursor, $templates->pageSize, $templates->isFirstPage))->toArray(),
            'onboardingPage' => (new PageSlice(array_map(static fn (RecruitmentOnboardingItem $item): array => [
                'id' => (string) $item->id,
                'name' => (string) $item->name,
                'description' => $item->description,
                'position' => (int) $item->position,
                'required' => (bool) $item->is_required,
                'active' => (bool) $item->is_active,
            ], $onboardingItems->items), $onboardingItems->nextCursor, $onboardingItems->pageSize, $onboardingItems->isFirstPage))->toArray(),
            'metrics' => $this->metrics->summary($allianceId),
        ];
    }

    /**
     * @param  array{q: string, stage: string, source: string}  $filters
     * @return PageSlice<array{id: string, name: string, email: string, contactHandle: string|null, source: string|null, stage: string, submittedAt: string, firstRespondedAt: string|null, nextActionAt: string|null}>
     */
    private function candidates(string $allianceId, array $filters, ?string $cursor): PageSlice
    {
        $query = RecruitmentCandidate::query()
            ->where('alliance_id', $allianceId)
            ->whereNull('merged_into_id')
            ->whereNull('anonymized_at');

        if ($filters['q'] !== '') {
            $search = $filters['q'];
            $query->where(static function (Builder $candidate) use ($search): void {
                $candidate
                    ->where('full_name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%")
                    ->orWhere('contact_handle', 'ilike', "%{$search}%");
            });
        }
        if ($filters['stage'] !== '') {
            $query->where('stage', $filters['stage']);
        }
        if ($filters['source'] !== '') {
            $query->where('source', $filters['source']);
        }

        $scope = $this->cursorScope($allianceId, $filters);
        if ($cursor !== null && $cursor !== '') {
            $position = $this->cursors->decode($cursor, $scope);
            $submittedAt = $position['submitted_at'] ?? null;
            $candidateId = $position['id'] ?? null;

            if (! is_string($submittedAt) || ! is_string($candidateId)) {
                throw ValidationException::withMessages([
                    'cursor' => 'The recruitment candidate cursor is incomplete.',
                ]);
            }

            $query->where(static function (Builder $candidate) use ($submittedAt, $candidateId): void {
                $candidate
                    ->where('submitted_at', '<', $submittedAt)
                    ->orWhere(static function (Builder $tie) use ($submittedAt, $candidateId): void {
                        $tie->where('submitted_at', '=', $submittedAt)->where('id', '<', $candidateId);
                    });
            });
        }

        $rows = $query
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->limit(self::CANDIDATE_PAGE_SIZE + 1)
            ->get();
        $hasMore = $rows->count() > self::CANDIDATE_PAGE_SIZE;
        $page = $rows->take(self::CANDIDATE_PAGE_SIZE)->values();

        $items = array_values($page->map(static fn (RecruitmentCandidate $candidate): array => [
            'id' => (string) $candidate->id,
            'name' => (string) $candidate->full_name,
            'email' => (string) $candidate->email,
            'contactHandle' => $candidate->contact_handle,
            'source' => $candidate->source,
            'stage' => $candidate->stage->value,
            'submittedAt' => $candidate->submitted_at->toIso8601String(),
            'firstRespondedAt' => $candidate->first_responded_at?->toIso8601String(),
            'nextActionAt' => $candidate->next_action_at?->toIso8601String(),
        ])->all());

        $nextCursor = null;
        $last = $page->last();
        if ($hasMore && $last instanceof RecruitmentCandidate) {
            $nextCursor = $this->cursors->encode($scope, [
                'submitted_at' => $last->submitted_at->toIso8601String(),
                'id' => (string) $last->id,
            ]);
        }

        return new PageSlice(
            $items,
            $nextCursor,
            self::CANDIDATE_PAGE_SIZE,
            $cursor === null || $cursor === '',
        );
    }

    /**
     * @template T of Model
     * @param  Builder<T>  $query
     * @return PageSlice<T>
     */
    private function cataloguePage(Builder $query, string $scope, string $column, ?string $cursor): PageSlice
    {
        if ($cursor !== null) {
            $position = $this->cursors->decode($cursor, $scope);
            $value = $position['value'] ?? null;
            $id = $position['id'] ?? null;
            if (! is_string($id) || ! preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/Di', $id)
                || ($column === 'position' ? (! is_int($value) || $value < 0 || $value > 65535) : (! is_string($value) || mb_strlen($value) > 120))) {
                throw ValidationException::withMessages(['cursor' => 'The recruitment catalogue cursor is invalid.']);
            }
            $query->where(static function (Builder $after) use ($column, $value, $id): void {
                $after->where($column, '>', $value)->orWhere(static function (Builder $tie) use ($column, $value, $id): void {
                    $tie->where($column, $value)->where('id', '>', $id);
                });
            });
        }
        $rows = $query->orderBy($column)->orderBy('id')->limit(26)->get();
        $items = array_values($rows->take(25)->all());
        $last = $items === [] ? null : $items[array_key_last($items)];
        $next = $rows->count() > 25 && $last instanceof Model
            ? $this->cursors->encode($scope, ['value' => $column === 'position' ? (int) $last->getAttribute($column) : (string) $last->getAttribute($column), 'id' => (string) $last->getKey()])
            : null;

        return new PageSlice($items, $next, 25, $cursor === null);
    }

    /**
     * @param  array{q?: string|null, stage?: string|null, source?: string|null}  $filters
     * @return array{q: string, stage: string, source: string}
     */
    private function normalizeFilters(array $filters): array
    {
        return [
            'q' => trim((string) ($filters['q'] ?? '')),
            'stage' => (string) ($filters['stage'] ?? ''),
            'source' => trim((string) ($filters['source'] ?? '')),
        ];
    }

    /** @param  array{q: string, stage: string, source: string}  $filters */
    private function cursorScope(string $allianceId, array $filters): string
    {
        return 'recruitment-candidates:'.$allianceId.':'.hash('sha256', json_encode($filters, JSON_THROW_ON_ERROR));
    }
}
