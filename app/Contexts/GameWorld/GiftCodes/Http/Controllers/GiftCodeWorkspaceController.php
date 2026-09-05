<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Http\Controllers;

use App\Contexts\Accounts\Identity\Contracts\AuthenticatedAccount;
use App\Contexts\GameWorld\GiftCodes\Actions\AbandonGiftCodeRedemptionSession;
use App\Contexts\GameWorld\GiftCodes\Actions\CreateGiftCodeRedemptionSession;
use App\Contexts\GameWorld\GiftCodes\Actions\PrepareGiftCodeRedemptionSessionItem;
use App\Contexts\GameWorld\GiftCodes\Actions\ReconcileGiftCodeRedemptionSession;
use App\Contexts\GameWorld\GiftCodes\Actions\RecordGiftCodeRedemptionSessionItemResult;
use App\Contexts\GameWorld\GiftCodes\Actions\SkipGiftCodeRedemptionSessionItem;
use App\Contexts\GameWorld\GiftCodes\Actions\UpdateGiftCodeAccountState;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeAccountStateStatus;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeRedemptionSessionItemState;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeRedemptionSessionMode;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeRedemptionSessionStatus;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCode;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeAccountState;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeRedemption;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeRedemptionSession;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeRedemptionSessionItem;
use App\Contexts\GameWorld\GiftCodes\Queries\GiftCodeWorkspaceQuery;
use App\Contexts\GameWorld\GiftCodes\Services\GiftCodeRedemptionSignalService;
use App\Contexts\GameWorld\GiftCodes\Services\GiftCodeRewardPresenter;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Shared\Infrastructure\AuditTrail\Contracts\AuditActor;
use App\Shared\Infrastructure\Http\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

final class GiftCodeWorkspaceController extends Controller
{
    public function index(
        Request $request,
        PlayerReferenceQuery $players,
        GiftCodeWorkspaceQuery $workspace,
        GiftCodeRewardPresenter $rewards,
        GiftCodeRedemptionSignalService $signals,
        ReconcileGiftCodeRedemptionSession $reconcile,
    ): Response {
        abort_unless((bool) config('game_world.gift_codes.redemption_workspace', false), 404);
        $account = $this->account($request);
        $actor = $this->actor($request);
        $userId = $this->accountId($account);
        $ownedPlayers = $players->ownedByUser($userId);
        abort_if($ownedPlayers === [], 409, 'At least one owned Governor is required for Gift Codes.');
        $playerIds = array_map(static fn (PlayerReference $player): string => $player->playerId, $ownedPlayers);

        $validated = $request->validate([
            'view' => ['sometimes', 'string', Rule::in(GiftCodeWorkspaceQuery::VIEWS)],
            'cursor' => ['nullable', 'string', 'max:2048'],
            'session' => ['nullable', 'string', 'ulid'],
        ]);
        $view = (string) ($validated['view'] ?? GiftCodeWorkspaceQuery::VIEW_READY);
        $page = $workspace->pageForAccount(
            $userId,
            $playerIds,
            $view,
            (int) config('game_world.gift_codes.workspace_page_size', 25),
            $validated['cursor'] ?? null,
        );
        $names = [];
        foreach ($ownedPlayers as $player) {
            $names[$player->playerId] = $player;
        }

        $session = $this->sessionForAccount($userId, $validated['session'] ?? null);
        if ($session instanceof GiftCodeRedemptionSession && $session->status === GiftCodeRedemptionSessionStatus::Active) {
            $session = $reconcile->handle($actor, (string) $session->id);
        }
        $signal = null;
        if ($session instanceof GiftCodeRedemptionSession) {
            $current = $session->items->first(static fn (GiftCodeRedemptionSessionItem $item): bool => in_array(
                $item->state,
                [GiftCodeRedemptionSessionItemState::Ready, GiftCodeRedemptionSessionItemState::AwaitingConfirmation],
                true,
            ));
            if ($current instanceof GiftCodeRedemptionSessionItem) {
                $signal = $signals->forGiftCode($current->gift_code_id);
            }
        }

        return Inertia::render('Kingdom/GiftCodes/Workspace', [
            'user' => ['name' => $account->accountName(), 'email' => $account->accountEmail()],
            'views' => GiftCodeWorkspaceQuery::VIEWS,
            'view' => $view,
            'counts' => $workspace->countsForAccount($userId, $playerIds),
            'codes' => array_map(
                fn (GiftCode $giftCode): array => $this->workspaceCode($giftCode, $rewards),
                array_values(array_filter($page->items(), static fn (mixed $item): bool => $item instanceof GiftCode)),
            ),
            'governors' => array_map(static fn (PlayerReference $player): array => [
                'id' => $player->playerId,
                'name' => $player->currentName,
                'gamePlayerId' => $player->gamePlayerId,
                'kingdomNumber' => $player->kingdomNumber,
            ], $ownedPlayers),
            'pagination' => [
                'nextCursor' => $page->nextCursor()?->encode(),
                'previousCursor' => $page->previousCursor()?->encode(),
            ],
            'session' => $session instanceof GiftCodeRedemptionSession
                ? $this->session($session, $names, $rewards)
                : null,
            'currentSignal' => $signal,
            'officialRedemptionUrl' => (string) config('game_world.gift_code_redemption_url'),
        ]);
    }

    public function createSession(Request $request, CreateGiftCodeRedemptionSession $create): RedirectResponse
    {
        $validated = $request->validate([
            'mode' => ['required', 'string', Rule::enum(GiftCodeRedemptionSessionMode::class)],
            'gift_code_ids' => ['sometimes', 'array', 'max:100'],
            'gift_code_ids.*' => ['string', 'ulid', 'distinct'],
            'player_ids' => ['sometimes', 'array', 'max:50'],
            'player_ids.*' => ['string', 'ulid', 'distinct'],
        ]);
        $session = $create->handle(
            $this->actor($request),
            GiftCodeRedemptionSessionMode::from($validated['mode']),
            array_values($validated['gift_code_ids'] ?? []),
            array_values($validated['player_ids'] ?? []),
        );

        return redirect('/gift-codes/workspace?session='.$session->id)
            ->with('actionReceipt', $this->receipt('gift-code-session-created', ['count' => $session->total_items]));
    }

    public function updateState(Request $request, string $giftCode, UpdateGiftCodeAccountState $update): RedirectResponse
    {
        $validated = $request->validate([
            'state' => ['required', 'string', Rule::enum(GiftCodeAccountStateStatus::class)],
            'snoozed_until' => ['nullable', 'date'],
            'remind_at' => ['nullable', 'date'],
        ]);
        $state = $update->handle(
            $this->actor($request),
            $giftCode,
            GiftCodeAccountStateStatus::from($validated['state']),
            isset($validated['snoozed_until']) ? CarbonImmutable::parse($validated['snoozed_until']) : null,
            isset($validated['remind_at']) ? CarbonImmutable::parse($validated['remind_at']) : null,
        );

        return back()->with('actionReceipt', $this->receipt('gift-code-personal-state-updated', [
            'state' => $state->state->value,
        ]));
    }

    public function prepareItem(
        Request $request,
        string $session,
        string $item,
        PrepareGiftCodeRedemptionSessionItem $prepare,
    ): RedirectResponse {
        $prepare->handle($this->actor($request), $session, $item);

        return redirect('/gift-codes/workspace?session='.$session)
            ->with('actionReceipt', $this->receipt('gift-code-session-item-prepared'));
    }

    public function resultItem(
        Request $request,
        string $session,
        string $item,
        RecordGiftCodeRedemptionSessionItemResult $record,
    ): RedirectResponse {
        $validated = $request->validate([
            'result' => ['required', 'string', Rule::in([
                'redeemed',
                'already_redeemed',
                'invalid',
                'expired',
                'wrong_kingdom',
                'rate_limited',
                'temporarily_unavailable',
                'permanent_failure',
            ])],
        ]);
        $record->handle($this->actor($request), $session, $item, $validated['result']);

        return redirect('/gift-codes/workspace?session='.$session)
            ->with('actionReceipt', $this->receipt('gift-code-session-item-recorded'));
    }

    public function skipItem(
        Request $request,
        string $session,
        string $item,
        SkipGiftCodeRedemptionSessionItem $skip,
    ): RedirectResponse {
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:120']]);
        $skip->handle($this->actor($request), $session, $item, (string) ($validated['reason'] ?? 'user_skipped'));

        return redirect('/gift-codes/workspace?session='.$session)
            ->with('actionReceipt', $this->receipt('gift-code-session-item-skipped'));
    }

    public function abandon(
        Request $request,
        string $session,
        AbandonGiftCodeRedemptionSession $abandon,
    ): RedirectResponse {
        $abandon->handle($this->actor($request), $session);

        return redirect('/gift-codes/workspace')
            ->with('actionReceipt', $this->receipt('gift-code-session-abandoned'));
    }

    /** @return array<string,mixed> */
    private function workspaceCode(GiftCode $giftCode, GiftCodeRewardPresenter $rewards): array
    {
        $personal = $giftCode->accountStates->first();

        return [
            'id' => (string) $giftCode->id,
            'code' => $giftCode->code,
            'status' => $giftCode->status->value,
            'statusRevision' => $giftCode->status_revision,
            'expiresAt' => $giftCode->expires_at?->toIso8601String(),
            'expiresRevision' => $giftCode->expires_revision,
            'sourceCount' => (int) ($giftCode->getAttribute('provenances_count') ?? 0),
            'reward' => $rewards->present($giftCode),
            'personalState' => $personal instanceof GiftCodeAccountState ? [
                'state' => $personal->state->value,
                'snoozedUntil' => $personal->snoozed_until?->toIso8601String(),
                'remindAt' => $personal->remind_at?->toIso8601String(),
            ] : null,
            'redemptions' => $giftCode->redemptions->map(static fn (GiftCodeRedemption $redemption): array => [
                'playerId' => $redemption->player_id,
                'status' => $redemption->status->value,
                'nextAttemptAt' => $redemption->next_attempt_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    /**
     * @param  array<string,PlayerReference>  $players
     * @return array<string,mixed>
     */
    private function session(
        GiftCodeRedemptionSession $session,
        array $players,
        GiftCodeRewardPresenter $rewards,
    ): array {
        return [
            'id' => (string) $session->id,
            'mode' => $session->mode->value,
            'status' => $session->status->value,
            'totalItems' => $session->total_items,
            'completedItems' => $session->completed_items,
            'skippedItems' => $session->skipped_items,
            'failedItems' => $session->failed_items,
            'lastActivityAt' => $session->last_activity_at->toIso8601String(),
            'items' => $session->items->map(function (GiftCodeRedemptionSessionItem $item) use ($players, $rewards): array {
                $player = $players[$item->player_id] ?? null;

                return [
                    'id' => (string) $item->id,
                    'giftCodeId' => $item->gift_code_id,
                    'code' => $item->giftCode->code,
                    'playerId' => $item->player_id,
                    'playerName' => $player?->currentName ?? $item->player_id,
                    'gamePlayerId' => $player?->gamePlayerId,
                    'kingdomNumber' => $player?->kingdomNumber,
                    'sequence' => $item->sequence,
                    'state' => $item->state->value,
                    'unavailableReason' => $item->unavailable_reason,
                    'skipReason' => $item->skip_reason,
                    'expiresAt' => $item->giftCode->expires_at?->toIso8601String(),
                    'reward' => $rewards->present($item->giftCode),
                ];
            })->values()->all(),
        ];
    }

    private function sessionForAccount(int $userId, ?string $sessionId): ?GiftCodeRedemptionSession
    {
        $query = GiftCodeRedemptionSession::query()
            ->where('user_id', $userId)
            ->with(['items.giftCode.factProjections']);
        if ($sessionId !== null && $sessionId !== '') {
            return $query->whereKey($sessionId)->firstOrFail();
        }

        return $query
            ->where('status', GiftCodeRedemptionSessionStatus::Active->value)
            ->orderByDesc('last_activity_at')
            ->first();
    }

    private function account(Request $request): AuthenticatedAccount
    {
        $account = $request->user();
        abort_unless($account instanceof AuthenticatedAccount, 401);

        return $account;
    }

    private function actor(Request $request): AuditActor
    {
        $account = $this->account($request);
        if (! $account instanceof AuditActor) {
            throw new LogicException('Authenticated accounts must provide an audit identity.');
        }

        return $account;
    }

    private function accountId(AuthenticatedAccount $account): int
    {
        $id = $account->getAuthIdentifier();
        abort_unless(is_numeric($id), 401);

        return (int) $id;
    }
}
