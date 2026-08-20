<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Recruitment\Http\Controllers;

use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Contexts\Alliance\Recruitment\Actions\BulkChangeRecruitmentStage;
use App\Contexts\Alliance\Recruitment\Actions\ConfigureRecruitmentSettings;
use App\Contexts\Alliance\Recruitment\Actions\CreateRecruitmentDecisionTemplate;
use App\Contexts\Alliance\Recruitment\Actions\CreateRecruitmentOnboardingItem;
use App\Contexts\Alliance\Recruitment\Actions\CreateRecruitmentQuestion;
use App\Contexts\Alliance\Recruitment\Actions\IssueRecruitmentApplicationInvite;
use App\Contexts\Alliance\Recruitment\Actions\PreviewRecruitmentStageBulkChange;
use App\Contexts\Alliance\Recruitment\Actions\UpdateRecruitmentQuestion;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentApplicationMode;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentQuestionType;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentStage;
use App\Shared\Infrastructure\Http\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class RecruitmentManagementController extends Controller
{
    public function updateSettings(
        Request $request,
        AllianceContext $context,
        ConfigureRecruitmentSettings $configure,
    ): RedirectResponse {
        $validated = $request->validate([
            'mode' => ['required', Rule::enum(RecruitmentApplicationMode::class)],
            'title' => ['required', 'string', 'max:160'],
            'introduction' => ['nullable', 'string', 'max:5000'],
            'retention_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'open' => ['required', 'boolean'],
            'listed' => ['required', 'boolean'],
        ]);

        $scope = $context->scope();
        $configure->handle(
            $scope->playerId,
            $scope->allianceId,
            RecruitmentApplicationMode::from($validated['mode']),
            $validated['title'],
            $validated['introduction'] ?? null,
            (int) $validated['retention_days'],
            (bool) $validated['open'],
            (bool) $validated['listed'],
        );

        return back()->with('actionReceipt', $this->receipt('recruitment-settings-updated'));
    }

    public function storeQuestion(
        Request $request,
        AllianceContext $context,
        CreateRecruitmentQuestion $create,
        UpdateRecruitmentQuestion $update,
    ): RedirectResponse {
        $validated = $request->validate([
            'question_id' => ['nullable', 'ulid'],
            'prompt' => ['required', 'string', 'max:240'],
            'help_text' => ['nullable', 'string', 'max:2000'],
            'type' => ['required', Rule::enum(RecruitmentQuestionType::class)],
            'options' => ['array', 'max:30'],
            'options.*' => ['string', 'max:160'],
            'required' => ['required', 'boolean'],
            'position' => ['required', 'integer', 'min:0', 'max:65535'],
            'active' => ['required', 'boolean'],
        ]);

        $scope = $context->scope();
        $type = RecruitmentQuestionType::from($validated['type']);
        $options = $validated['options'] ?? [];

        if (isset($validated['question_id'])) {
            $update->handle(
                $scope->playerId,
                $scope->allianceId,
                (string) $validated['question_id'],
                $validated['prompt'],
                $type,
                (bool) $validated['required'],
                (int) $validated['position'],
                $validated['help_text'] ?? null,
                $options,
                (bool) $validated['active'],
            );

            return back()->with('actionReceipt', $this->receipt('recruitment-question-updated'));
        }

        $create->handle(
            $scope->playerId,
            $scope->allianceId,
            $validated['prompt'],
            $type,
            (bool) $validated['required'],
            (int) $validated['position'],
            $validated['help_text'] ?? null,
            $options,
            (bool) $validated['active'],
        );

        return back()->with('actionReceipt', $this->receipt('recruitment-question-created'));
    }

    public function issueApplicationInvite(
        Request $request,
        AllianceContext $context,
        IssueRecruitmentApplicationInvite $issue,
        AllianceReferenceQuery $alliances,
    ): RedirectResponse {
        $validated = $request->validate([
            'email' => ['nullable', 'email:rfc', 'max:320'],
            'ttl_hours' => ['required', 'integer', 'min:1', 'max:720'],
        ]);
        $scope = $context->scope();
        $alliance = $alliances->require($scope->allianceId);
        $issued = $issue->handle(
            $scope->playerId,
            $scope->allianceId,
            $validated['email'] ?? null,
            (int) $validated['ttl_hours'],
        );

        $request->session()->flash('recruitmentApplicationLink', route('public.alliances.recruitment.show', [
            'slug' => $alliance->slug,
            'token' => $issued->token,
        ]));

        return back()->with('actionReceipt', $this->receipt('recruitment-application-invite-issued'));
    }

    public function storeDecisionTemplate(
        Request $request,
        AllianceContext $context,
        CreateRecruitmentDecisionTemplate $create,
    ): RedirectResponse {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'decision_stage' => ['required', Rule::in([RecruitmentStage::Accepted->value, RecruitmentStage::Declined->value])],
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:10000'],
            'active' => ['required', 'boolean'],
        ]);

        $scope = $context->scope();
        $create->handle(
            $scope->playerId,
            $scope->allianceId,
            $validated['name'],
            RecruitmentStage::from($validated['decision_stage']),
            $validated['subject'],
            $validated['body'],
            (bool) $validated['active'],
        );

        return back()->with('actionReceipt', $this->receipt('recruitment-decision-template-created'));
    }

    public function storeOnboardingItem(
        Request $request,
        AllianceContext $context,
        CreateRecruitmentOnboardingItem $create,
    ): RedirectResponse {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'position' => ['required', 'integer', 'min:0', 'max:65535'],
            'required' => ['required', 'boolean'],
            'active' => ['required', 'boolean'],
        ]);

        $scope = $context->scope();
        $create->handle(
            $scope->playerId,
            $scope->allianceId,
            $validated['name'],
            $validated['description'] ?? null,
            (int) $validated['position'],
            (bool) $validated['required'],
            (bool) $validated['active'],
        );

        return back()->with('actionReceipt', $this->receipt('recruitment-onboarding-item-created'));
    }

    public function previewBulkStageChange(
        Request $request,
        AllianceContext $context,
        PreviewRecruitmentStageBulkChange $preview,
    ): RedirectResponse {
        $validated = $this->validateBulkStageChange($request);
        $scope = $context->scope();

        /** @var non-empty-list<string> $candidateIds */
        $candidateIds = array_values($validated['candidate_ids']);
        $request->session()->flash('recruitmentBulkPreview', $preview->handle(
            $scope->playerId,
            $scope->allianceId,
            $candidateIds,
            RecruitmentStage::from($validated['stage']),
        ));

        return back();
    }

    public function commitBulkStageChange(
        Request $request,
        AllianceContext $context,
        AllianceReferenceQuery $alliances,
        BulkChangeRecruitmentStage $change,
    ): RedirectResponse {
        $validated = $this->validateBulkStageChange($request);
        $scope = $context->scope();
        $alliance = $alliances->require($scope->allianceId);
        $nextActionAt = isset($validated['next_action_at']) && is_string($validated['next_action_at'])
            ? CarbonImmutable::parse($validated['next_action_at'], $alliance->timezone)
            : null;

        /** @var non-empty-list<string> $candidateIds */
        $candidateIds = array_values($validated['candidate_ids']);
        $result = $change->handle(
            $scope->playerId,
            $scope->allianceId,
            $candidateIds,
            RecruitmentStage::from($validated['stage']),
            isset($validated['reason']) && is_string($validated['reason'])
                ? $validated['reason']
                : null,
            $nextActionAt,
        )->toArray();

        $request->session()->flash('recruitmentBulkResult', $result);

        return back()->with('actionReceipt', $this->receipt('recruitment-bulk-stage-completed', [
            'succeeded' => $result['succeeded'],
            'failed' => $result['failed'],
            'skipped' => $result['skipped'],
        ]));
    }

    /**
     * @return array{
     *   candidate_ids: list<string>,
     *   stage: string,
     *   reason?: string|null,
     *   next_action_at?: string|null
     * }
     */
    private function validateBulkStageChange(Request $request): array
    {
        return $request->validate([
            'candidate_ids' => ['required', 'array', 'min:1', 'max:50'],
            'candidate_ids.*' => ['required', 'ulid', 'distinct'],
            'stage' => [
                'required',
                Rule::enum(RecruitmentStage::class),
                Rule::notIn([RecruitmentStage::Joined->value]),
            ],
            'reason' => ['nullable', 'string', 'max:5000'],
            'next_action_at' => ['nullable', 'date'],
        ]);
    }
}
