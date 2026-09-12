<?php

declare(strict_types=1);

namespace Tests\Contexts\Platform\AllianceAdministration\Feature;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Alliance\Content\Policies\StorageCapacityPolicy;
use App\Contexts\Alliance\Lifecycle\Actions\CreateAlliance;
use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\Alliance\Membership\Policies\MemberCapacityPolicy;
use App\Contexts\Platform\Administration\Actions\ManagePlatformAdministrator;
use App\Contexts\Platform\AllianceAdministration\Actions\ConfigureAlliancePlatform;
use App\Contexts\Platform\AllianceAdministration\Actions\InitializeAlliancePlatform;
use App\Contexts\Platform\AllianceAdministration\Queries\PlanEntitlementQuery;
use App\Contexts\Platform\Integrations\Policies\IntegrationCapacityPolicy;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class AlliancePlatformOwnershipV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_initialization_retries_preserve_administrative_plan_and_settings_changes(): void
    {
        $alliance = $this->alliance();
        $account = app(ScenarioFactory::class)->account();
        app(ManagePlatformAdministrator::class)->grant($account->userId);
        $actor = app(AccountIdentityQuery::class)->require($account->userId);
        $this->customPlan();
        app(ConfigureAlliancePlatform::class)->assignPlan($actor, (string) $alliance->id, 'custom');
        app(ConfigureAlliancePlatform::class)->updateSettings($actor, (string) $alliance->id, 365, 'high-volume', false, false);
        $before = $this->state();
        $this->travel(1)->hours();
        app(InitializeAlliancePlatform::class)->handle((string) $alliance->id);
        app(InitializeAlliancePlatform::class)->handle((string) $alliance->id);
        self::assertSame($before, $this->state());
    }

    public function test_partial_initialization_failure_rolls_back_both_records_and_can_retry(): void
    {
        $alliance = $this->alliance();
        DB::table('alliance_plan_assignments')->where('alliance_id', $alliance->id)->delete();
        DB::table('alliance_platform_settings')->where('alliance_id', $alliance->id)->delete();
        $before = $this->state();
        $this->failSettingsOnce();
        try {
            app(InitializeAlliancePlatform::class)->handle((string) $alliance->id);
            self::fail('Initialization must be atomic.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected Platform settings failure.', $exception->getMessage());
        }
        self::assertSame($before, $this->state());
        app(InitializeAlliancePlatform::class)->handle((string) $alliance->id);
        self::assertSame('standard', DB::table('alliance_plan_assignments')->where('alliance_id', $alliance->id)->value('plan_code'));
        $settings = DB::table('alliance_platform_settings')->where('alliance_id', $alliance->id)->first();
        self::assertNotNull($settings);
        self::assertSame(30, (int) $settings->retention_days);
        self::assertSame('standard', $settings->queue_partition);
        self::assertTrue((bool) $settings->api_access_enabled);
        self::assertTrue((bool) $settings->webhooks_enabled);
    }

    public function test_platform_initialization_failure_rolls_back_the_composed_alliance_creation(): void
    {
        $factory = app(ScenarioFactory::class);
        $account = $factory->account();
        $player = $factory->player($account->userId);
        $before = $this->state();
        $this->failSettingsOnce();
        try {
            app(CreateAlliance::class)->handle($account->userId, $player->playerId, 'Atomic Alliance', 'atomic-alliance');
            self::fail('A Platform failure must roll back the Alliance owner transaction.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected Platform settings failure.', $exception->getMessage());
        }
        self::assertSame($before, $this->state());
    }

    public function test_all_capacity_owners_use_the_assigned_custom_plan_and_bounded_limit_projection(): void
    {
        $alliance = $this->alliance();
        $this->customPlan();
        DB::table('alliance_plan_assignments')->where('alliance_id', $alliance->id)->update(['plan_code' => 'custom']);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $limits = app(PlanEntitlementQuery::class)->limits((string) $alliance->id);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        self::assertCount(2, $queries);
        self::assertSame(['members' => 2, 'storageBytes' => 5, 'apiCredentials' => 0, 'webhookSubscriptions' => 0], $limits);
        self::assertSame(1, app(MemberCapacityPolicy::class)->remainingCapacity($alliance));
        app(MemberCapacityPolicy::class)->assertCapacity($alliance);
        app(StorageCapacityPolicy::class)->assertCapacity($alliance, 5);
        foreach (['storage', 'api', 'webhooks'] as $owner) {
            try {
                $this->capacity($owner, $alliance);
                self::fail('The custom plan must constrain '.$owner.'.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey($owner === 'storage' ? 'media' : 'quota', $exception->errors());
            }
        }
    }

    /** @return iterable<string,array{string,string}> */
    public static function missingEntitlements(): iterable
    {
        yield 'members' => ['members', 'members.max'];
        yield 'storage' => ['storage', 'storage.bytes.max'];
        yield 'api' => ['api', 'api_credentials.max'];
        yield 'webhooks' => ['webhooks', 'webhook_subscriptions.max'];
    }

    #[DataProvider('missingEntitlements')]
    public function test_missing_entitlements_have_one_owner_error_contract(string $owner, string $key): void
    {
        $alliance = $this->alliance();
        DB::table('platform_plan_entitlements')->where('plan_code', 'standard')->where('entitlement_key', $key)->delete();
        $before = $this->state();
        try {
            $this->capacity($owner, $alliance);
            self::fail('A missing entitlement must be explicit.');
        } catch (ValidationException $exception) {
            self::assertSame(['plan' => ['The current plan does not define the '.$key.' entitlement.']], $exception->errors());
        }
        self::assertSame($before, $this->state());
    }

    public function test_unassigned_alliance_uses_the_same_default_plan_contract(): void
    {
        $alliance = $this->alliance();
        $expected = app(PlanEntitlementQuery::class)->limits((string) $alliance->id);
        DB::table('alliance_plan_assignments')->where('alliance_id', $alliance->id)->delete();
        self::assertSame($expected, app(PlanEntitlementQuery::class)->limits((string) $alliance->id));
        self::assertSame($expected['members'] - 1, app(MemberCapacityPolicy::class)->remainingCapacity($alliance));
    }

    private function alliance(): Alliance
    {
        $factory = app(ScenarioFactory::class);
        $account = $factory->account();

        return Alliance::query()->findOrFail($factory->alliance($factory->player($account->userId))->allianceId);
    }

    private function customPlan(): void
    {
        DB::table('platform_plans')->insert(['code' => 'custom', 'name' => 'Custom', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        foreach (['members.max' => 2, 'storage.bytes.max' => 5, 'api_credentials.max' => 0, 'webhook_subscriptions.max' => 0] as $key => $limit) {
            DB::table('platform_plan_entitlements')->insert(['plan_code' => 'custom', 'entitlement_key' => $key, 'limit_value' => $limit, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function capacity(string $owner, Alliance $alliance): void
    {
        match ($owner) {
            'members' => app(MemberCapacityPolicy::class)->assertCapacity($alliance),
            'storage' => app(StorageCapacityPolicy::class)->assertCapacity($alliance, 6),
            'api' => app(IntegrationCapacityPolicy::class)->assertApiCredentialCapacity((string) $alliance->id),
            'webhooks' => app(IntegrationCapacityPolicy::class)->assertWebhookCapacity((string) $alliance->id),
        };
    }

    private function failSettingsOnce(): void
    {
        $failed = false;
        DB::listen(static function (QueryExecuted $query) use (&$failed): void {
            if (! $failed && str_starts_with($query->sql, 'insert into "alliance_platform_settings"')) {
                $failed = true;
                throw new RuntimeException('Injected Platform settings failure.');
            }
        });
    }

    /** @return array<string,string> */
    private function state(): array
    {
        $state = [];
        foreach (['alliances' => 'id', 'alliance_memberships' => 'id', 'roles' => 'id', 'alliance_plan_assignments' => 'alliance_id', 'alliance_platform_settings' => 'alliance_id', 'audit_events' => 'id', 'outbox_messages' => 'id'] as $table => $key) {
            $state[$table] = DB::table($table)->orderBy($key)->get()->toJson();
        }

        return $state;
    }
}
