<?php

declare(strict_types=1);

namespace App\Domain\Recruitment\Http\Controllers;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Alliances\Services\AllianceContext;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Identity\Models\User;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use App\Domain\Platform\Http\Controllers\Controller;
use App\Domain\Recruitment\Actions\ConfigureRecruitmentSettings;
use App\Domain\Recruitment\Actions\CreateRecruitmentDecisionTemplate;
use App\Domain\Recruitment\Actions\CreateRecruitmentOnboardingItem;
use App\Domain\Recruitment\Actions\CreateRecruitmentQuestion;
use App\Domain\Recruitment\Actions\IssueRecruitmentApplicationInvite;
use App\Domain\Recruitment\Actions\UpdateRecruitmentQuestion;
use App\Domain\Recruitment\Enums\RecruitmentApplicationMode;
use App\Domain\Recruitment\Enums\RecruitmentQuestionType;
use App\Domain\Recruitment\Enums\RecruitmentStage;
use App\Domain\Recruitment\Models\RecruitmentCandidate;
use App\Domain\Recruitment\Models\RecruitmentDecisionTemplate;
use App\Domain\Recruitment\Models\RecruitmentOnboardingItem;
use App\Domain\Recruitment\Models\RecruitmentQuestion;
use App\Domain\Recruitment\Models\RecruitmentSetting;
use App\Domain\Recruitment\Queries\RecruitmentMetricsQuery;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

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
        $this->authorize($authorization, $user, $alliance->id, $alliance);

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
            ->with('user:id,name')
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
            $member = $membership->user;
            if (! $member instanceof User) {
                throw new LogicException('An active recruitment reviewer membership must reference a user.');
            }

            $memberData[] = [
                'id' => (string) $membership->id,
                'name' => (string) $member->name,
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
            $this->user($request),
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

        $actor = $this->user($request);
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
            $this->user($request),
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
            $this->user($request),
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
            $this->user($request),
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
        User $user,
        string $allianceId,
        Alliance $alliance,
    ): void {
        if ($alliance->id !== $allianceId || ! $authorization->allows($user, $alliance, PermissionKey::RecruitmentManage)) {
            throw new AuthorizationException;
        }
    }
}
