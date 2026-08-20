<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Http\Controllers;

use App\Contexts\Accounts\Identity\Contracts\AuthenticatedAccount;
use App\Contexts\GameWorld\GiftCodes\Actions\ConfirmGiftCodeRedemption;
use App\Contexts\GameWorld\GiftCodes\Actions\PrepareGiftCodeRedemptions;
use App\Contexts\GameWorld\GiftCodes\Actions\ReportGiftCodeIssue;
use App\Contexts\GameWorld\GiftCodes\Actions\SubmitGiftCode;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCode;
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
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

final class GiftCodeController extends Controller
{
    public function index(
        Request $request,
        PlayerContext $playerContext,
        PlayerReferenceQuery $players,
        GiftCodeCatalogQuery $catalog,
    ): Response {
        $account = $this->account($request);
        $player = $this->player($playerContext);
        $ownedPlayers = $players->ownedByUser($this->accountId($account));
        $ownedPlayerIds = array_map(static fn (PlayerReference $owned): string => $owned->playerId, $ownedPlayers);
        /** @var non-empty-list<string> $ownedPlayerIds */
        $codes = $catalog->forPlayers($ownedPlayerIds);
        $playerNames = [];
        foreach ($ownedPlayers as $ownedPlayer) {
            $playerNames[$ownedPlayer->playerId] = $ownedPlayer->currentName;
        }

        return Inertia::render('Kingdom/GiftCodes/Index', [
            'user' => [
                'name' => $account->accountName(),
                'email' => $account->accountEmail(),
            ],
            'player' => [
                'id' => $player->playerId,
                'name' => $player->currentName,
                'gamePlayerId' => $player->gamePlayerId,
                'kingdomNumber' => $player->kingdomNumber,
            ],
            'governors' => array_map(static fn (PlayerReference $owned): array => [
                'id' => $owned->playerId,
                'name' => $owned->currentName,
                'gamePlayerId' => $owned->gamePlayerId,
                'kingdomNumber' => $owned->kingdomNumber,
            ], $ownedPlayers),
            'officialRedemptionUrl' => (string) config('game_world.gift_code_redemption_url'),
            'codes' => $codes->map(function (GiftCode $giftCode) use ($catalog, $player, $playerNames): array {
                $redemption = $catalog->redemptionFor($giftCode, $player->playerId);

                return [
                    'id' => (string) $giftCode->id,
                    'code' => $giftCode->code,
                    'sourceType' => $giftCode->source_type->value,
                    'sourceLabel' => $giftCode->source_label,
                    'sourceUrl' => $giftCode->source_url,
                    'status' => $giftCode->status->value,
                    'discoveredAt' => $giftCode->discovered_at->toIso8601String(),
                    'expiresAt' => $giftCode->expires_at?->toIso8601String(),
                    'redemption' => $this->redemption($redemption),
                    'redemptions' => $giftCode->redemptions->map(fn (GiftCodeRedemption $item): array => [
                        'playerId' => $item->player_id,
                        'playerName' => $playerNames[$item->player_id] ?? $item->player_id,
                        ...$this->redemptionData($item),
                    ])->values()->all(),
                    'provenances' => $giftCode->provenances->map(static fn (GiftCodeProvenance $provenance): array => [
                        'id' => (string) $provenance->id,
                        'sourceType' => $provenance->source_type->value,
                        'sourceLabel' => $provenance->source_label,
                        'sourceUrl' => $provenance->source_url,
                        'observedAt' => $provenance->observed_at->toIso8601String(),
                    ])->values()->all(),
                ];
            })->all(),
            'giftCodeRedemptionResult' => $request->session()->get('giftCodeRedemptionResult'),
        ]);
    }

    public function store(Request $request, PlayerContext $playerContext, SubmitGiftCode $submit): RedirectResponse
    {
        /** @var array{code: string, source_type: string, source_label?: string|null, source_url?: string|null, expires_at?: string|null} $validated */
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:64'],
            'source_type' => ['required', 'in:manual,official,community'],
            'source_label' => ['nullable', 'string', 'max:160'],
            'source_url' => ['nullable', 'url:http,https', 'max:2048'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $result = $submit->handle($this->player($playerContext), $validated);

        return back()->with('actionReceipt', $this->receipt(
            $result->duplicateDetected ? 'gift-code-duplicate-recorded' : 'gift-code-added',
        ));
    }

    public function redeem(
        Request $request,
        string $giftCode,
        PrepareGiftCodeRedemptions $prepare,
    ): RedirectResponse {
        /** @var array{player_ids: non-empty-list<string>} $validated */
        $validated = $request->validate([
            'player_ids' => ['required', 'array', 'min:1', 'max:50'],
            'player_ids.*' => ['required', 'string', 'ulid', 'distinct'],
        ]);
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

        return back()
            ->with('giftCodeRedemptionResult', $result)
            ->with('actionReceipt', $this->receipt('gift-code-handoff-prepared', [
                'succeeded' => $result['succeeded'],
                'failed' => $result['failed'],
                'skipped' => $result['skipped'],
            ]));
    }

    public function confirm(
        string $giftCode,
        PlayerContext $playerContext,
        ConfirmGiftCodeRedemption $confirm,
    ): RedirectResponse {
        $confirm->handle($giftCode, $this->player($playerContext));

        return back()->with('actionReceipt', $this->receipt('gift-code-confirmed'));
    }

    public function report(
        Request $request,
        string $giftCode,
        PlayerContext $playerContext,
        ReportGiftCodeIssue $report,
    ): RedirectResponse {
        /** @var array{issue: string} $validated */
        $validated = $request->validate(['issue' => ['required', 'string', 'in:invalid,expired']]);
        $report->handle($giftCode, $this->player($playerContext), $validated['issue']);

        return back()->with('actionReceipt', $this->receipt('gift-code-issue-reported'));
    }

    /** @return array<string, mixed>|null */
    private function redemption(?GiftCodeRedemption $redemption): ?array
    {
        if (! $redemption instanceof GiftCodeRedemption) {
            return null;
        }

        return $this->redemptionData($redemption);
    }

    /** @return array<string, mixed> */
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
}
