<?php

declare(strict_types=1);

namespace App\ReadModels\RecruitmentManagement\Http\Controllers;

use App\Contexts\Accounts\Identity\Contracts\AuthenticatedAccount;
use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\ReadModels\RecruitmentManagement\Queries\RecruitmentCandidateDetailQuery;
use App\ReadModels\RecruitmentManagement\Queries\RecruitmentCandidateSelectionQuery;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class RecruitmentCandidateReadController extends Controller
{
    public function __invoke(Request $request, AllianceContext $context, RecruitmentCandidateDetailQuery $details, string $candidate): Response
    {
        $user = $request->user();
        abort_unless($user instanceof AuthenticatedAccount, 401);
        $validated = $request->validate([
            'notes_cursor' => ['nullable', 'string', 'max:4096'],
            'history_cursor' => ['nullable', 'string', 'max:4096'],
            'communications_cursor' => ['nullable', 'string', 'max:4096'],
            'duplicates_cursor' => ['nullable', 'string', 'max:4096'],
            'tags_cursor' => ['nullable', 'string', 'max:4096'],
            'reviewers_cursor' => ['nullable', 'string', 'max:4096'],
        ]);
        $scope = $context->scope();
        $projection = $details->forCandidate($scope->playerId, $scope->allianceId, $candidate, [
            'notes' => $validated['notes_cursor'] ?? null,
            'history' => $validated['history_cursor'] ?? null,
            'communications' => $validated['communications_cursor'] ?? null,
            'duplicates' => $validated['duplicates_cursor'] ?? null,
            'tags' => $validated['tags_cursor'] ?? null,
            'reviewers' => $validated['reviewers_cursor'] ?? null,
        ]);

        return Inertia::render('Alliance/Recruitment/Candidate', [
            ...$projection,
            'user' => ['name' => $user->accountName(), 'email' => $user->accountEmail()],
            'issuedMembershipInvitationLink' => $request->session()->pull('recruitmentMembershipInvitationLink'),
        ]);
    }

    public function options(Request $request, AllianceContext $context, RecruitmentCandidateSelectionQuery $choices, string $candidate, string $kind): JsonResponse
    {
        abort_unless($request->user() instanceof AuthenticatedAccount, 401);
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:160'],
            'cursor' => ['nullable', 'string', 'max:4096'],
            'selected' => ['nullable', 'ulid'],
        ]);
        $scope = $context->scope();

        return response()->json($choices->forCandidate(
            $scope->playerId, $scope->allianceId, $candidate, $kind,
            $validated['q'] ?? '', $validated['cursor'] ?? null, $validated['selected'] ?? null,
        ));
    }
}
