<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Alliance\Lifecycle;

use App\Contexts\Alliance\Lifecycle\Actions\UpdateAllianceSettings;
use App\Contexts\Alliance\Lifecycle\Enums\SupportedAllianceLocale;
use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Shared\Infrastructure\AuditTrail\Models\AuditEvent;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class AllianceSettingsBehaviorV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_r5_can_update_application_owned_alliance_settings_and_records_evidence(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->authUser();
        $owner = $scenario->player((int) $account->id, 59001);
        $alliance = $scenario->alliance($owner);

        app(UpdateAllianceSettings::class)->handle(
            $alliance->allianceId,
            $owner->playerId,
            'Updated Alliance',
            'Updated Alliance URL',
            SupportedAllianceLocale::French,
            'America/Toronto',
        );

        $record = Alliance::query()->findOrFail($alliance->allianceId);
        self::assertSame('Updated Alliance', $record->name);
        self::assertSame('updated-alliance-url', $record->slug);
        self::assertSame('fr', $record->language);
        self::assertSame('America/Toronto', $record->timezone);
        self::assertTrue(AuditEvent::query()
            ->where('alliance_id', $alliance->allianceId)
            ->where('event', 'alliance.settings_changed')
            ->where('actor_player_id', $owner->playerId)
            ->exists());
    }

    public function test_active_governor_cannot_use_another_alliance_scope_to_change_settings(): void
    {
        $scenario = new ScenarioFactory;
        $firstAccount = $scenario->authUser();
        $firstOwner = $scenario->player((int) $firstAccount->id, 59002);
        $firstAlliance = $scenario->alliance($firstOwner);
        $secondAccount = $scenario->authUser();
        $secondOwner = $scenario->player((int) $secondAccount->id, 59003);
        $secondAlliance = $scenario->alliance($secondOwner);

        $this->expectException(AuthorizationException::class);

        app(UpdateAllianceSettings::class)->handle(
            $secondAlliance->allianceId,
            $firstOwner->playerId,
            'Cross Alliance Attempt',
            'cross-alliance-attempt',
            SupportedAllianceLocale::English,
            'UTC',
        );

        self::assertNotSame($firstAlliance->allianceId, $secondAlliance->allianceId);
    }

    public function test_reserved_slug_is_rejected_before_any_alliance_write(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->authUser();
        $owner = $scenario->player((int) $account->id, 59004);
        $alliance = $scenario->alliance($owner);

        try {
            app(UpdateAllianceSettings::class)->handle(
                $alliance->allianceId,
                $owner->playerId,
                'Still Original',
                'admin',
                SupportedAllianceLocale::English,
                'UTC',
            );
            self::fail('Expected the reserved Alliance slug to be rejected.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('slug', $exception->errors());
        }

        $record = Alliance::query()->findOrFail($alliance->allianceId);
        self::assertSame($alliance->name, $record->name);
        self::assertSame($alliance->slug, $record->slug);
        self::assertFalse(AuditEvent::query()
            ->where('alliance_id', $alliance->allianceId)
            ->where('event', 'alliance.settings_changed')
            ->exists());
    }
}
