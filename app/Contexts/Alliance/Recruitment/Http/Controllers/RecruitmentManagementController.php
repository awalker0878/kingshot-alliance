<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Recruitment\Http\Controllers;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Alliance\Core\Services\AllianceContext;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Recruitment\Actions\ConfigureRecruitmentSettings;
use App\Contexts\Alliance\Recruitment\Actions\CreateRecruitmentDecisionTemplate;
use App\Contexts\Alliance\Recruitment\Actions\CreateRecruitmentOnboardingItem;
use App\Contexts\Alliance\Recruitment\Actions\CreateRecruitmentQuestion;
use App\Contexts\Alliance\Recruitment\Actions\IssueRecruitmentApplicationInvite;
use App\Contexts\Alliance\Recruitment\Actions\UpdateRecruitmentQuestion;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentApplicationMode;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentQuestionType;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentStage;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCandidate;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentDecisionTemplate;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentOnboardingItem;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentQuestion;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentSetting;
use App\Contexts\Alliance\Recruitment\Queries\RecruitmentMetricsQuery;
use App\Contexts\GameWorld\Models\Player;
use App\Shared\Http\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class RecruitmentManagementController extends Controller
{
    public function index(
        Request $request,
        AllianceContext $context,
        AllianceAuthorization $authorization,
        RecruitmentMetricsQuery $metrics,
    ): Response {
        $user = $this->user($request);
        $alliance = $context->alliance();
        $this->authorize($authorization, $context->player(), $alliance);

        $settings = RecruitmentSetting::query()->where('alliance_id', $alliance->id)->first();
        $questions = RecruitmentQuestion::query()
            ->where('alliance_id', $alliance->id)
            ->orderBy('position')
            ->orderBy('id')
            ->get();
        $candidates = RecruitmentCandidate::query()
            ->where('alliance_id', $alliance->id)
            ->whereNull('merged_into_id')
            ->whereNull('anonymized_at')
            ->orderByDesc('submitted_at')
            ->limit(250)
            ->get();
        $memberships = AllianceMembership::query()
            ->where('alliance_id', $alliance->id)
            ->where('status', MembershipStatus::Active->value)
            ->with('player:id,current_name')
            ->orderBy('created_at')
            ->get();
        $templates = RecruitmentDecisionTemplate::query()
            ->where('alliance_id', $alliance->id)
            ->orderBy('name')
            ->get();
        $onboardingItems = RecruitmentOnboardingItem::query()
            ->where('alliance_id', $alliance->id)
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        $questionData = [];
        foreach ($questions as $question) {
            $questionData[] = [
                'id' => (string) $question->id,
                'prompt' => (string) $question->prompt,
                'helpText' => $question->help_text,
                'type' => $question->type()->value,
                'options' => $question->optionValues(),
                'required' => (bool) $question->is_required,
                'position' => (int) $question->position,
                'active' => (bool) $question->is_active,
            ];
        }

        $candidateData = [];
        foreach ($candidates as $candidate) {
            $candidateData[] = [
                'id' => (string) $candidate->id,
                'name' => (string) $candidate->full_name,
                'email' => (string) $candidate->email,
                'contactHandle' => $candidate->contact_handle,
                'source' => $candidate->source,
                'stage' => $candidate->stage->value,
                'submittedAt' => $candidate->submitted_at->toIso8601String(),
                'firstRespondedAt' => $candidate->first_responded_at?->toIso8601String(),
                'nextActionAt' => $candidate->next_action_at?->toIso8601String(),
            ];
        }

        $memberData = [];
        foreach ($memberships as $membership) {
            $player = $membership->player;
            if (! $player instanceof Player) {
                continue;
            }

            $memberData[] = [
                'id' => (string) $player->id,
                'name' => (string) $player->current_name,
                'rank' => $membership->rank->value,
            ];
        }

        $templateData = [];
        foreach ($templates as $template) {
            $templateData[] = [
                'id' => (string) $template->id,
                'name' => (string) $template->name,
                'decisionStage' => $template->decisionStage()->value,
                'subject' => (string) $template->subject,
                'body' => (string) $template->body,
                'active' => (bool) $template->is_active,
            ];
        }

        $onboardingData = [];
        foreach ($onboardingItems as $item) {
            $onboardingData[] = [
                'id' => (string) $item->id,
                'name' => (string) $item->name,
                'description' => $item->description,
                'position' => (int) $item->position,
                'required' => (bool) $item->is_required,
                'active' => (bool) $item->is_active,
            ];
        }

        return Inertia::render('Alliance/Recruitment/Manage', [
            'user' => [
                'name' => (string) $user->name,
                'email' => (string) $user->email,
            ],
            'alliance' => [
                'id' => (string) $alliance->id,
                'name' => (string) $alliance->name,
                'slug' => (string) $alliance->slug,
            ],
            'settings' => $settings instanceof RecruitmentSetting ? [
                'mode' => $settings->application_mode->value,
                'title' => (string) $settings->title,
                'introduction' => $settings->introduction,
                'retentionDays' => (int) $settings->retention_unsuccessful_days,
                'open' => (bool) $settings->is_open,
            ] : null,
            'applicationModes' => array_map(
                static fn (RecruitmentApplicationMode $mode): string => $mode->value,
                RecruitmentApplicationMode::cases(),
            ),
            'questionTypes' => array_map(
                static fn (RecruitmentQuestionType $type): string => $type->value,
                RecruitmentQuestionType::cases(),
            ),
            'candidateStages' => array_map(
                static fn (RecruitmentStage $stage): string => $stage->value,
                RecruitmentStage::cases(),
            ),
            'questions' => $questionData,
            'candidates' => $candidateData,
            'members' => $memberData,
            'decisionTemplates' => $templateData,
            'onboardingItems' => $onboardingData,
            'metrics' => $metrics->summary($alliance),
            'issuedApplicationLink' => $request->session()->pull('recruitmentApplicationLink'),
        ]);
    }

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
        ]);

        $configure->handle(
            $context->player(),
            $context->alliance(),
            RecruitmentApplicationMode::from($validated['mode']),
            $validated['title'],
            $validated['introduction'] ?? null,
            (int) $validated['retention_days'],
            (bool) $validated['open'],
        );

        return back();
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

        $actor = $context->player();
        $alliance = $context->alliance();
        $type = RecruitmentQuestionType::from($validated['type']);
        $options = $validated['options'] ?? [];

        if (isset($validated['question_id'])) {
            $question = RecruitmentQuestion::query()
                ->where('alliance_id', $alliance->id)
                ->whereKey($validated['question_id'])
                ->firstOrFail();

            $update->handle(
                $actor,
                $alliance,
                $question,
                $validated['prompt'],
                $type,
                (bool) $validated['required'],
                (int) $validated['position'],
                $validated['help_text'] ?? null,
                $options,
                (bool) $validated['active'],
            );

            return back();
        }

        $create->handle(
            $actor,
            $alliance,
            $validated['prompt'],
            $type,
            (bool) $validated['required'],
            (int) $validated['position'],
            $validated['help_text'] ?? null,
            $options,
            (bool) $validated['active'],
        );

        return back();
    }

    public function issueApplicationInvite(
        Request $request,
        AllianceContext $context,
        IssueRecruitmentApplicationInvite $issue,
    ): RedirectResponse {
        $validated = $request->validate([
            'email' => ['nullable', 'email:rfc', 'max:320'],
            'ttl_hours' => ['required', 'integer', 'min:1', 'max:720'],
        ]);
        $alliance = $context->alliance();
        $issued = $issue->handle(
            $context->player(),
            $alliance,
            $validated['email'] ?? null,
            (int) $validated['ttl_hours'],
        );

        $request->session()->flash('recruitmentApplicationLink', route('public.alliances.recruitment.show', [
            'slug' => $alliance->slug,
            'token' => $issued->token,
        ]));

        return back();
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

        $create->handle(
            $context->player(),
            $context->alliance(),
            $validated['name'],
            RecruitmentStage::from($validated['decision_stage']),
            $validated['subject'],
            $validated['body'],
            (bool) $validated['active'],
        );

        return back();
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

        $create->handle(
            $context->player(),
            $context->alliance(),
            $validated['name'],
            $validated['description'] ?? null,
            (int) $validated['position'],
            (bool) $validated['required'],
            (bool) $validated['active'],
        );

        return back();
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    private function authorize(
        AllianceAuthorization $authorization,
        Player $actor,
        Alliance $alliance,
    ): void {
        if (! $authorization->allows($actor, $alliance, AlliancePermission::RecruitmentManage)) {
            throw new AuthorizationException;
        }
    }
}
