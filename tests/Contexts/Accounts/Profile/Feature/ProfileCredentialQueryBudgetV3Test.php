<?php

declare(strict_types=1);

namespace Tests\Contexts\Accounts\Profile\Feature;

use App\Contexts\Accounts\Authentication\Services\AccountSignInMethodPolicy;
use App\Contexts\Accounts\Identity\Models\AccountIdentity;
use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Accounts\Profile\Http\Controllers\ProfileController;
use App\Contexts\Accounts\Security\Queries\AccountSecurityActivityQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ProfileCredentialQueryBudgetV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_profile_queries_stay_constant_as_owned_passkeys_grow_and_exclude_foreign_methods(): void
    {
        $user = User::factory()->create(['password' => null]);
        $first = $this->passkey($user);
        $foreign = User::factory()->google()->create();
        $foreignKey = $this->passkey($foreign);

        [$single, $singleQueries] = $this->profile($user);
        self::assertSame(1, $single['user']['signInMethodCount']);
        self::assertFalse($single['user']['canRemovePassword']);
        self::assertFalse($single['user']['canDisconnectGoogle']);
        self::assertSame([$first], array_column($single['passkeys'], 'id'));
        self::assertFalse($single['passkeys'][0]['canRemove']);

        for ($index = 1; $index < 40; $index++) {
            $this->passkey($user);
        }
        [$many, $manyQueries] = $this->profile($user);

        self::assertLessThanOrEqual(6, $singleQueries);
        self::assertSame($singleQueries, $manyQueries, 'Profile credential cardinality must not add database round trips.');
        self::assertSame(40, $many['user']['signInMethodCount']);
        self::assertSame(40, $many['user']['passkeyCount']);
        self::assertCount(40, $many['passkeys']);
        self::assertNotContains($foreignKey, array_column($many['passkeys'], 'id'));
        foreach ($many['passkeys'] as $passkey) {
            self::assertTrue($passkey['canRemove']);
        }
    }

    public function test_profile_uses_the_same_summary_for_password_and_google_removal(): void
    {
        $user = User::factory()->create();
        [$passwordOnly] = $this->profile($user);
        self::assertSame(1, $passwordOnly['user']['signInMethodCount']);
        self::assertFalse($passwordOnly['user']['canRemovePassword']);
        self::assertFalse($passwordOnly['user']['canDisconnectGoogle']);
        self::assertSame([], $passwordOnly['passkeys']);

        AccountIdentity::query()->create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_subject' => 'profile-budget-google',
            'provider_email' => 'profile-budget@example.test',
            'provider_email_verified_at' => now(),
            'linked_at' => now(),
        ]);
        [$both] = $this->profile($user);
        self::assertSame(2, $both['user']['signInMethodCount']);
        self::assertTrue($both['user']['canRemovePassword']);
        self::assertTrue($both['user']['canDisconnectGoogle']);
        self::assertSame('profile-budget@example.test', $both['user']['providerEmail']);
    }

    public function test_profile_preserves_a_final_google_method(): void
    {
        [$profile] = $this->profile(User::factory()->google()->create());
        self::assertSame(1, $profile['user']['signInMethodCount']);
        self::assertFalse($profile['user']['passwordAuthentication']);
        self::assertTrue($profile['user']['googleAuthentication']);
        self::assertFalse($profile['user']['canRemovePassword']);
        self::assertFalse($profile['user']['canDisconnectGoogle']);
        self::assertSame([], $profile['passkeys']);
    }

    /** @return array{array<string,mixed>,int} */
    private function profile(User $user): array
    {
        $request = Request::create('/profile');
        $request->headers->set('X-Inertia', 'true');
        $request->setUserResolver(static fn (): User => $user);
        $request->setLaravelSession(app('session.store'));
        $controller = app(ProfileController::class);
        $activity = app(AccountSecurityActivityQuery::class);
        $policy = app(AccountSignInMethodPolicy::class);

        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $response = $controller->show($request, $activity, $policy);
            $queries = count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }

        $httpResponse = $response->toResponse($request);
        self::assertInstanceOf(JsonResponse::class, $httpResponse);

        return [$httpResponse->getData(true)['props'], $queries];
    }

    private function passkey(User $user): string
    {
        $id = (string) Str::uuid();
        DB::table('passkeys')->insert([
            'public_id' => $id,
            'user_id' => $user->id,
            'name' => 'Profile passkey',
            'credential_id' => 'profile-budget-'.$id,
            'credential' => json_encode(['test' => true], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }
}
