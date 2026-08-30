<?php

declare(strict_types=1);

namespace Tests\v3\ReadModels\BotCommands;

use App\Contexts\Alliance\Content\Enums\ContentStatus;
use App\Contexts\Alliance\Content\Enums\ContentType;
use App\Contexts\Alliance\Content\Enums\ContentVisibility;
use App\Contexts\Alliance\Content\Models\ContentItem;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentApplicationMode;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentSetting;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeStatus;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCode;
use App\Contexts\Platform\Integrations\Actions\CreateApiCredential;
use App\Contexts\Platform\Integrations\Models\ApiCredential;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class BotCommandApiBehaviorV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_command_reads_are_bounded_tenant_scoped_and_scope_protected(): void
    {
        $scenarios = app(ScenarioFactory::class);
        $account = $scenarios->account();
        $player = $scenarios->player($account->userId, 43001);
        $alliance = $scenarios->alliance($player);

        RecruitmentSetting::query()->create([
            'alliance_id' => $alliance->allianceId,
            'application_mode' => RecruitmentApplicationMode::Public,
            'title' => 'Join the command',
            'retention_unsuccessful_days' => 90,
            'is_open' => true,
            'is_listed' => false,
            'created_by_player_id' => $player->playerId,
            'updated_by_player_id' => $player->playerId,
        ]);

        GiftCode::query()->create([
            'code' => 'BOT-COMMAND',
            'normalized_code' => 'BOT-COMMAND',
            'created_by_player_id' => $player->playerId,
            'status' => GiftCodeStatus::Valid,
            'status_revision' => 1,
            'status_reason_code' => 'qualified_positive_evidence',
            'status_evidence_ids' => [],
            'status_changed_at' => now(),
            'status_derived_at' => now(),
            'discovered_at' => now()->subHour(),
            'expires_at' => now()->addDay(),
            'expires_precision' => 'day',
            'expires_revision' => 1,
        ]);
        GiftCode::query()->create([
            'code' => 'BOT-COMMAND-LATER',
            'normalized_code' => 'BOT-COMMAND-LATER',
            'status' => GiftCodeStatus::Valid,
            'status_revision' => 1,
            'status_reason_code' => 'qualified_positive_evidence',
            'status_evidence_ids' => [],
            'status_changed_at' => now(),
            'status_derived_at' => now(),
            'discovered_at' => now()->subHours(2),
            'expires_at' => now()->addDays(2),
            'expires_precision' => 'day',
            'expires_revision' => 1,
        ]);
        GiftCode::query()->create([
            'code' => 'EXPIRED-COMMAND',
            'normalized_code' => 'EXPIRED-COMMAND',
            'status' => GiftCodeStatus::Expired,
            'status_revision' => 1,
            'status_reason_code' => 'accepted_expiry_elapsed',
            'status_evidence_ids' => [],
            'status_changed_at' => now()->subDay(),
            'status_derived_at' => now()->subDay(),
            'discovered_at' => now()->subDays(2),
            'expires_at' => now()->subDay(),
            'expires_precision' => 'day',
            'expires_revision' => 1,
        ]);

        ContentItem::query()->create([
            'alliance_id' => $alliance->allianceId,
            'type' => ContentType::Guide,
            'visibility' => ContentVisibility::Members,
            'status' => ContentStatus::Published,
            'title' => 'Bear Hunt positions',
            'slug' => 'bear-hunt-positions',
            'summary' => 'Current formation notes.',
            'body' => '<p>Use the published rally order.</p>',
            'locale' => 'en',
            'sort_order' => 0,
            'current_revision_number' => 1,
            'notify_members' => false,
            'source_label' => 'Alliance review',
            'game_version' => '2026.08',
            'reviewed_at' => now()->toDateString(),
            'published_at' => now()->subMinute(),
            'created_by_player_id' => $player->playerId,
            'updated_by_player_id' => $player->playerId,
        ]);

        $underScoped = $this->credential(
            $alliance->allianceId,
            $player->playerId,
            ['events:read'],
        );
        $this->withToken($underScoped)
            ->getJson(route('api.v1.commands.overview'))
            ->assertUnauthorized();

        $commandToken = $this->credential(
            $alliance->allianceId,
            $player->playerId,
            ['commands:read'],
        );
        $this->withToken($commandToken)
            ->getJson(route('api.v1.commands.overview'))
            ->assertOk()
            ->assertJsonPath('data.alliance.id', $alliance->allianceId)
            ->assertJsonPath('data.gift_codes.0.code', 'BOT-COMMAND')
            ->assertJsonPath('data.knowledge.0.title', 'Bear Hunt positions')
            ->assertJsonPath('data.recruitment.status', 'open')
            ->assertJsonPath('meta.read_only', true)
            ->assertJsonCount(0, 'data.events');

        $overview = $this->withToken($commandToken)
            ->getJson(route('api.v1.commands.overview'))
            ->json('data.recruitment.application_url');
        self::assertIsString($overview);
        self::assertStringContainsString('source=bot-command', $overview);

        $readToken = $this->credential(
            $alliance->allianceId,
            $player->playerId,
            ['gift-codes:read', 'content:read'],
        );
        $firstGiftCodePage = $this->withToken($readToken)
            ->getJson(route('api.v1.gift-codes.index', ['limit' => 1]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'BOT-COMMAND')
            ->assertJsonPath('data.0.trust_status', 'valid')
            ->assertJsonPath('data.0.status_revision', 1)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.status', 'active');
        $nextCursor = $firstGiftCodePage->json('meta.next_cursor');
        self::assertIsString($nextCursor);
        $this->withToken($readToken)
            ->getJson(route('api.v1.gift-codes.index', ['limit' => 1, 'cursor' => $nextCursor]))
            ->assertOk()
            ->assertJsonPath('data.0.code', 'BOT-COMMAND-LATER');

        $this->withToken($readToken)
            ->getJson(route('api.v1.gift-codes.index', ['status' => 'pending']))
            ->assertForbidden();

        $this->withToken($readToken)
            ->getJson(route('api.v1.commands.knowledge', ['q' => 'bear', 'type' => 'guide']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.visibility', 'members')
            ->assertJsonPath('data.0.source_label', 'Alliance review');

        self::assertContains('commands:read', CreateApiCredential::allowedScopes());
        self::assertNotNull(ApiCredential::query()->where('prefix', substr($commandToken, 8, 12))->value('last_used_at'));
    }

    /** @param  list<string>  $scopes */
    private function credential(string $allianceId, string $playerId, array $scopes): string
    {
        $prefix = bin2hex(random_bytes(6));
        $secret = bin2hex(random_bytes(32));

        ApiCredential::query()->create([
            'alliance_id' => $allianceId,
            'name' => 'Bot command test',
            'prefix' => $prefix,
            'secret_hash' => hash('sha256', $secret),
            'scopes' => $scopes,
            'created_by_player_id' => $playerId,
        ]);

        return 'ks_live_'.$prefix.'.'.$secret;
    }
}
