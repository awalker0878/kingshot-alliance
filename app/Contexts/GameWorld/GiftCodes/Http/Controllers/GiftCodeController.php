<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Http\Controllers;

use App\Contexts\Accounts\Identity\Contracts\AuthenticatedAccount;
use App\Contexts\GameWorld\GiftCodes\Actions\PrepareGiftCodeRedemptions;
use App\Contexts\GameWorld\GiftCodes\Actions\RecordObservedGiftCodeRedemptionResult;
use App\Contexts\GameWorld\GiftCodes\Actions\SubmitGiftCode;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCode;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeModerationDecision;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeProvenance;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeRedemption;
use App\Contexts\GameWorld\GiftCodes\Queries\GiftCodeCatalogQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\GameWorld\Players\Services\PlayerContext;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Shared\Infrastructure\AuditTrail\Contracts\AuditActor;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

final class GiftCodeController extends Controller
{
    public function index(Request $request, PlayerContext $context, PlayerReferenceQuery $players, GiftCodeCatalogQuery $catalog): Response
    {
        return $this->render($request, $context, $players, $catalog, null);
    }

    public function show(Request $request, string $giftCode, PlayerContext $context, PlayerReferenceQuery $players, GiftCodeCatalogQuery $catalog): Response
    {
        return $this->render($request, $context, $players, $catalog, $giftCode);
    }

    public function store(Request $request, PlayerContext $context, SubmitGiftCode $submit): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:64'],
            'source_type' => ['required', Rule::in(['manual', 'community'])],
            'source_label' => ['nullable', 'string', 'max:160'],
            'source_url' => ['nullable', 'url:http,https', 'max:2048'],
            'expires_at' => ['nullable', 'date'],
            'expiry_precision' => ['nullable', Rule::in(['instant', 'minute', 'hour', 'day'])],
            'expiry_timezone' => ['nullable', 'timezone'],
        ]);

        /** @var array{code:string,source_type:string,source_label?:string|null,source_url?:string|null,expires_at?:string|null,expiry_precision?:string|null,expiry_timezone?:string|null} $validated */
        $result = $submit->handle($this->player($context), $validated);

        return redirect('/gift-codes/'.$result->giftCode->giftCodeId)
            ->with('actionReceipt', $this->receipt(
                $result->duplicateDetected ? 'gift-code-duplicate-recorded' : 'gift-code-added',
            ));
    }

    public function redeem(Request $request, string $giftCode, PrepareGiftCodeRedemptions $prepare): RedirectResponse
    {
        $validated = $request->validate([
            'player_ids' => ['required', 'array', 'min:1', 'max:50'],
            'player_ids.*' => ['required', 'string', 'ulid', 'distinct'],
        ]);
        /** @var array{player_ids: non-empty-list<string>} $validated */
        $account = $this->account($request);
        if (! $account instanceof AuditActor) {
            throw new LogicException('Authenticated accounts must provide an audit identity.');
        }

        $result = $prepare->handle(
            $account,
            $this->accountId($account),
            $giftCode,
            $validated['player_ids'],
        )->toArray();

        return redirect('/gift-codes/'.$giftCode)
            ->with('giftCodeRedemptionResult', $result)
            ->with('actionReceipt', $this->receipt('gift-code-handoff-prepared', [
                'succeeded' => $result['succeeded'],
                'failed' => $result['failed'],
                'skipped' => $result['skipped'],
            ]));
    }

    public function result(
        Request $request,
        string $giftCode,
        PlayerContext $context,
        PlayerReferenceQuery $players,
        RecordObservedGiftCodeRedemptionResult $record,
    ): RedirectResponse {
        $validated = $request->validate([
            'player_id' => ['nullable', 'string', 'ulid'],
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
        /** @var array{player_id?:string|null,result:string} $validated */
        $target = $this->targetPlayer($request, $context, $players, $validated['player_id'] ?? null);
        $record->handle($giftCode, $target, $validated['result']);

        return redirect('/gift-codes/'.$giftCode)->with('actionReceipt', $this->receipt('gift-code-result-recorded', [
            'player_id' => $target->playerId,
            'result' => $validated['result'],
        ]));
    }

    private function render(
        Request $request,
        PlayerContext $context,
        PlayerReferenceQuery $players,
        GiftCodeCatalogQuery $catalog,
        ?string $focusedGiftCodeId,
    ): Response {
        $account = $this->account($request);
        $activePlayer = $this->player($context);
        $ownedPlayers = $players->ownedByUser($this->accountId($account));
        $ownedPlayerIds = array_values(array_map(static fn (PlayerReference $owned): string => $owned->playerId, $ownedPlayers));
        abort_if($ownedPlayerIds === [], 409, 'At least one owned Governor is required for Gift Codes.');

        $validated = $request->validate([
            'view' => ['sometimes', 'string', Rule::in(GiftCodeCatalogQuery::VIEWS)],
            'q' => ['nullable', 'string', 'max:64'],
            'status' => ['nullable', 'string', 'max:32'],
            'source' => ['nullable', 'string', 'max:160'],
            'expiry' => ['nullable', Rule::in(['none', '24h', '7d', 'expired'])],
            'governor_result' => ['nullable', 'string', 'max:48'],
            'cursor' => ['nullable', 'string', 'max:2048'],
        ]);
        /** @var array{view?:string,q?:string|null,status?:string|null,source?:string|null,expiry?:string|null,governor_result?:string|null,cursor?:string|null} $validated */
        $filters = [
            'view' => $validated['view'] ?? GiftCodeCatalogQuery::VIEW_ACTIVE,
            'q' => $validated['q'] ?? '',
            'status' => $validated['status'] ?? '',
            'source' => $validated['source'] ?? '',
            'expiry' => $validated['expiry'] ?? '',
            'governor_result' => $validated['governor_result'] ?? '',
        ];
        $page = $catalog->pageForPlayers(
            $ownedPlayerIds,
            $filters,
            (int) config('game_world.gift_codes.catalog_page_size', 25),
            $validated['cursor'] ?? null,
        );

        $playerNames = [];
        foreach ($ownedPlayers as $ownedPlayer) {
            $playerNames[$ownedPlayer->playerId] = $ownedPlayer->currentName;
        }
        $focused = $focusedGiftCodeId === null ? null : $catalog->detailForPlayers($focusedGiftCodeId, $ownedPlayerIds);

        return Inertia::render('Kingdom/GiftCodes/Index', [
            'user' => ['name' => $account->accountName(), 'email' => $account->accountEmail()],
            'player' => $this->governor($activePlayer),
            'governors' => array_map(fn (PlayerReference $owned): array => $this->governor($owned), $ownedPlayers),
            'officialRedemptionUrl' => (string) config('game_world.gift_code_redemption_url'),
            'codes' => array_values(array_map(
                fn (GiftCode $code): array => $this->catalogItem($code, $activePlayer->playerId, $playerNames),
                $page->items(),
            )),
            'pagination' => [
                'nextCursor' => $page->nextCursor()?->encode(),
                'previousCursor' => $page->previousCursor()?->encode(),
                'perPage' => $page->perPage(),
                'hasMore' => $page->hasMorePages(),
            ],
            'filters' => [
                ...$filters,
                'governorResult' => $filters['governor_result'],
            ],
            'focusedCode' => $focused instanceof GiftCode
                ? $this->detailItem($focused, $activePlayer->playerId, $playerNames)
                : null,
            'giftCodeRedemptionResult' => $request->session()->get('giftCodeRedemptionResult'),
        ]);
    }

    /** @return array{id:string,name:string,gamePlayerId:string|null,kingdomNumber:int|null} */
    private function governor(PlayerReference $player): array
    {
        return [
            'id' => $player->playerId,
            'name' => $player->currentName,
            'gamePlayerId' => $player->gamePlayerId,
            'kingdomNumber' => $player->kingdomNumber,
        ];
    }

    /** @param array<string,string> $playerNames */
    private function catalogItem(GiftCode $giftCode, string $activePlayerId, array $playerNames): array
    {
        return [
            'id' => (string) $giftCode->id,
            'code' => $giftCode->code,
            'status' => $giftCode->status->value,
            'statusReasonCode' => $giftCode->status_reason_code,
            'statusRevision' => $giftCode->status_revision,
            'sourceCount' => (int) ($giftCode->getAttribute('provenances_count') ?? 0),
            'discoveredAt' => $giftCode->discovered_at->toIso8601String(),
            'expiresAt' => $giftCode->expires_at?->toIso8601String(),
            'expiresPrecision' => $giftCode->expires_precision,
            'expiresRevision' => $giftCode->expires_revision,
            'redemption' => $this->redemption($this->redemptionForLoaded($giftCode, $activePlayerId)),
            'redemptions' => $giftCode->redemptions->map(fn (GiftCodeRedemption $item): array => [
                'playerId' => $item->player_id,
                'playerName' => $playerNames[$item->player_id] ?? $item->player_id,
                ...$this->redemptionData($item),
            ])->values()->all(),
        ];
    }

    /** @param array<string,string> $playerNames */
    private function detailItem(GiftCode $giftCode, string $activePlayerId, array $playerNames): array
    {
        return [
            ...$this->catalogItem($giftCode, $activePlayerId, $playerNames),
            'statusEvidenceIds' => $giftCode->status_evidence_ids ?? [],
            'provenances' => $giftCode->provenances->map(static fn (GiftCodeProvenance $provenance): array => [
                'id' => (string) $provenance->id,
                'registeredSourceId' => $provenance->registered_source_id,
                'registeredSourceName' => $provenance->registeredSource?->name,
                'sourceType' => $provenance->source_type->value,
                'sourceLabel' => $provenance->source_label,
                'sourceUrl' => $provenance->source_url,
                'assertion' => $provenance->assertion,
                'assertionPayload' => $provenance->assertion_payload,
                'verificationState' => $provenance->verification_state->value,
                'evidenceClassification' => $provenance->evidence_classification->value,
                'claimedExpiresAt' => $provenance->claimed_expires_at?->toIso8601String(),
                'expiryPrecision' => $provenance->expiry_precision,
                'publishedAt' => $provenance->published_at?->toIso8601String(),
                'observedAt' => $provenance->observed_at->toIso8601String(),
            ])->values()->all(),
            'moderationDecisions' => $giftCode->moderationDecisions->map(static fn (GiftCodeModerationDecision $decision): array => [
                'id' => (string) $decision->id,
                'action' => $decision->action->value,
                'reason' => $decision->reason,
                'evidenceIds' => $decision->evidence_ids ?? [],
                'decidedAt' => $decision->decided_at->toIso8601String(),
            ])->values()->all(),
        ];
    }

    private function redemptionForLoaded(GiftCode $giftCode, string $playerId): ?GiftCodeRedemption
    {
        $redemption = $giftCode->redemptions->first(static fn (GiftCodeRedemption $candidate): bool => $candidate->player_id === $playerId);

        return $redemption instanceof GiftCodeRedemption ? $redemption : null;
    }

    /** @return array<string,mixed>|null */
    private function redemption(?GiftCodeRedemption $redemption): ?array
    {
        return $redemption instanceof GiftCodeRedemption ? $this->redemptionData($redemption) : null;
    }

    /** @return array<string,mixed> */
    private function redemptionData(GiftCodeRedemption $redemption): array
    {
        return [
            'status' => $redemption->status->value,
            'attempts' => $redemption->attempts,
            'resultCode' => $redemption->last_result_code,
            'message' => $redemption->last_message,
            'redemptionUrl' => $redemption->redemption_url,
            'lastAttemptAt' => $redemption->last_attempt_at?->toIso8601String(),
            'nextAttemptAt' => $redemption->next_attempt_at?->toIso8601String(),
            'redeemedAt' => $redemption->redeemed_at?->toIso8601String(),
        ];
    }

    private function account(Request $request): AuthenticatedAccount
    {
        $account = $request->user();
        abort_unless($account instanceof AuthenticatedAccount, 401);

        return $account;
    }

    private function accountId(AuthenticatedAccount $account): int
    {
        $id = $account->getAuthIdentifier();
        abort_unless(is_numeric($id), 401);

        return (int) $id;
    }

    private function player(PlayerContext $context): PlayerReference
    {
        $player = $context->playerOrNull();
        abort_unless($player instanceof PlayerReference, 409, 'Select a Governor before opening Gift Codes.');

        return $player;
    }

    private function targetPlayer(Request $request, PlayerContext $context, PlayerReferenceQuery $players, ?string $targetPlayerId): PlayerReference
    {
        if ($targetPlayerId === null || $targetPlayerId === '') {
            return $this->player($context);
        }

        $player = $players->findOwnedByUser($this->accountId($this->account($request)), $targetPlayerId);
        if (! $player instanceof PlayerReference) {
            throw ValidationException::withMessages(['player_id' => 'That Governor is no longer owned by this account.']);
        }

        return $player;
    }
}
