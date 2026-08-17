<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Recruitment\Http\Controllers;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Alliance\Lifecycle\Enums\AllianceStatus;
use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\Alliance\Recruitment\Actions\SubmitRecruitmentApplication;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentApplicationMode;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentApplicationInvite;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentQuestion;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentSetting;
use App\Contexts\Alliance\Recruitment\Services\RecruitmentApplicationTokenService;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class PublicRecruitmentController extends Controller
{
    public function show(
        Request $request,
        RecruitmentApplicationTokenService $tokens,
        KingdomReferenceQuery $kingdoms,
        string $slug,
    ): Response {
        $alliance = $this->alliance($slug);
        $kingdom = $kingdoms->find((string) $alliance->kingdom_id);
        $settings = RecruitmentSetting::query()->where('alliance_id', $alliance->id)->first();
        $applicationToken = $request->string('token')->toString();
        $tokenValid = false;
        $tokenEmail = null;

        if ($settings instanceof RecruitmentSetting
            && $settings->is_open
            && $settings->application_mode === RecruitmentApplicationMode::Invitation) {
            $invite = $this->validInvite($alliance, $tokens, $applicationToken);
            abort_unless($invite instanceof RecruitmentApplicationInvite, 404);
            $tokenValid = true;
            $tokenEmail = $invite->email;
        }

        $isOpen = $settings instanceof RecruitmentSetting
            && $settings->is_open
            && $settings->application_mode !== RecruitmentApplicationMode::Closed
            && ($settings->application_mode !== RecruitmentApplicationMode::Invitation || $tokenValid);

        $questionData = [];
        if ($isOpen) {
            $questions = RecruitmentQuestion::query()
                ->where('alliance_id', $alliance->id)
                ->where('is_active', true)
                ->orderBy('position')
                ->orderBy('id')
                ->get();

            foreach ($questions as $question) {
                $questionData[] = [
                    'id' => (string) $question->id,
                    'prompt' => (string) $question->prompt,
                    'helpText' => $question->help_text,
                    'type' => $question->type()->value,
                    'options' => $question->optionValues(),
                    'required' => (bool) $question->is_required,
                ];
            }
        }

        $user = $request->user();

        return Inertia::render('Public/Recruitment/Apply', [
            'alliance' => [
                'name' => (string) $alliance->name,
                'slug' => (string) $alliance->slug,
                'kingdom' => $kingdom?->number,
            ],
            'application' => [
                'open' => $isOpen,
                'mode' => $settings?->application_mode->value ?? RecruitmentApplicationMode::Closed->value,
                'title' => $settings instanceof RecruitmentSetting ? (string) $settings->title : 'Recruitment applications are closed',
                'introduction' => $settings?->introduction,
                'token' => $tokenValid ? $applicationToken : null,
            ],
            'questions' => $questionData,
            'prefill' => [
                'name' => $user instanceof User ? (string) $user->name : '',
                'email' => $tokenEmail ?? ($user instanceof User ? (string) $user->email : ''),
                'emailLocked' => $tokenEmail !== null,
            ],
            'submitted' => (bool) $request->session()->pull('recruitmentApplicationSubmitted', false),
        ]);
    }

    public function store(
        Request $request,
        SubmitRecruitmentApplication $submit,
        string $slug,
    ): RedirectResponse {
        $alliance = $this->alliance($slug);
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email:rfc', 'max:320'],
            'contact_handle' => ['nullable', 'string', 'max:160'],
            'source' => ['nullable', 'string', 'max:120'],
            'application_token' => ['nullable', 'string', 'size:64'],
            'answers' => ['array'],
        ]);
        $user = $request->user();

        $submit->handle(
            allianceId: (string) $alliance->id,
            fullName: $validated['full_name'],
            email: $validated['email'],
            answers: $validated['answers'] ?? [],
            contactHandle: $validated['contact_handle'] ?? null,
            source: $validated['source'] ?? null,
            applicationToken: $validated['application_token'] ?? null,
            applicantUserId: $user instanceof User ? (int) $user->id : null,
        );

        $request->session()->flash('recruitmentApplicationSubmitted', true);

        return redirect()->route('public.alliances.recruitment.show', [
            'slug' => $alliance->slug,
            'token' => $validated['application_token'] ?? null,
        ]);
    }

    private function alliance(string $slug): Alliance
    {
        return Alliance::query()
            ->where('slug', $slug)
            ->where('status', AllianceStatus::Active->value)
            ->firstOrFail();
    }

    private function validInvite(
        Alliance $alliance,
        RecruitmentApplicationTokenService $tokens,
        string $token,
    ): ?RecruitmentApplicationInvite {
        if (! preg_match('/^[A-Fa-f0-9]{64}$/', $token)) {
            return null;
        }

        return RecruitmentApplicationInvite::query()
            ->where('alliance_id', $alliance->id)
            ->where('token_hash', $tokens->hash($token))
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();
    }
}
