<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Http\Controllers;

use App\Contexts\Accounts\Identity\Contracts\AuthenticatedAccount;
use App\Contexts\GameWorld\GiftCodes\Actions\BeginGiftCodeRedemption;
use App\Contexts\GameWorld\GiftCodes\Actions\ConfirmGiftCodeRedemption;
use App\Contexts\GameWorld\GiftCodes\Actions\SubmitGiftCode;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCode;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeRedemption;
use App\Contexts\GameWorld\GiftCodes\Queries\GiftCodeCatalogQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\GameWorld\Players\Services\PlayerContext;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

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
        $codes = $catalog->forPlayer($player->playerId);

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
            'allGovernorCount' => count($players->ownedByUser($this->accountId($account))),
            'officialRedemptionUrl' => (string) config('game_world.gift_code_redemption_url'),
            'codes' => $codes->map(function (GiftCode $giftCode) use ($catalog, $player): array {
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
                ];
            })->all(),
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

        $submit->handle($this->player($playerContext), $validated);

        return back()->with('status', 'Gift Code added to the shared ledger.');
    }

    public function redeem(
        Request $request,
        string $giftCode,
        PlayerContext $playerContext,
        PlayerReferenceQuery $players,
        BeginGiftCodeRedemption $begin,
    ): RedirectResponse {
        /** @var array{scope: string} $validated */
        $validated = $request->validate(['scope' => ['required', 'in:current,all']]);
        $account = $this->account($request);
        $targets = $validated['scope'] === 'all'
            ? $players->ownedByUser($this->accountId($account))
            : [$this->player($playerContext)];

        foreach ($targets as $target) {
            $begin->handle($giftCode, $target);
        }

        return back()->with('status', 'Official redemption handoff prepared.');
    }

    public function confirm(
        string $giftCode,
        PlayerContext $playerContext,
        ConfirmGiftCodeRedemption $confirm,
    ): RedirectResponse {
        $confirm->handle($giftCode, $this->player($playerContext));

        return back()->with('status', 'Gift Code marked as redeemed for the active Governor.');
    }

    /** @return array<string, mixed>|null */
    private function redemption(?GiftCodeRedemption $redemption): ?array
    {
        if (! $redemption instanceof GiftCodeRedemption) {
            return null;
        }

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
