<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Http\Controllers;

use App\Contexts\Alliance\Content\Actions\RemoveNoticeReaction;
use App\Contexts\Alliance\Content\Actions\SetNoticeReaction;
use App\Contexts\Alliance\Content\Enums\NoticeReaction;
use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class NoticeReactionController extends Controller
{
    public function update(
        Request $request,
        AllianceContext $context,
        SetNoticeReaction $setReaction,
        string $content,
    ): RedirectResponse {
        $validated = $request->validate([
            'reaction' => ['required', Rule::enum(NoticeReaction::class)],
        ]);
        $scope = $context->scope();

        $setReaction->handle(
            $scope->allianceId,
            $scope->playerId,
            $content,
            NoticeReaction::from((string) $validated['reaction']),
        );

        return back();
    }

    public function destroy(
        Request $request,
        AllianceContext $context,
        RemoveNoticeReaction $removeReaction,
        string $content,
    ): RedirectResponse {
        $scope = $context->scope();
        $removeReaction->handle($scope->allianceId, $scope->playerId, $content);

        return back();
    }
}
