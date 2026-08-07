<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Recruitment\RecruitmentApplicationTokenService;
use App\Application\Recruitment\SubmitRecruitmentApplication;
use App\Domain\Identity\Enums\AllianceStatus;
use App\Domain\Recruitment\Enums\RecruitmentApplicationMode;
use App\Models\Alliance;
use App\Models\RecruitmentApplicationInvite;
use App\Models\RecruitmentQuestion;
use App\Models\RecruitmentSetting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class PublicRecruitmentController extends Controller
{
    public function show(
        Request $request,
        RecruitmentApplicationTokenService $tokens,
        string $slug,
    ): Response {
        $alliance = $this->alliance($slug);
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

        return Inertia::render('Public/RecruitmentApply', [
            'alliance' => [
                'name' => (string) $alliance->name,
                'slug' => (string) $alliance->slug,
                'kingdom' => $alliance->kingdom,
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
            alliance: $alliance,
            fullName: $validated['full_name'],
            email: $validated['email'],
            answers: $validated['answers'] ?? [],
            contactHandle: $validated['contact_handle'] ?? null,
            source: $validated['source'] ?? null,
            applicationToken: $validated['application_token'] ?? null,
            applicant: $user instanceof User ? $user : null,
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
