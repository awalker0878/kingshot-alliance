<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Accounts\Authentication;

use App\Contexts\Accounts\Authentication\Services\AccountSignInMethodPolicy;
use App\Contexts\Accounts\Credentials\Actions\RemovePassword;
use App\Contexts\Accounts\Identity\Models\AccountIdentity;
use App\Contexts\Accounts\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\v3\TestCase;

final class SignInMethodPolicyV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_schema_has_no_authentication_type_column(): void
    {
        self::assertFalse(Schema::hasColumn('users', 'authentication_type'));
    }

    public function test_method_summary_is_derived_from_attached_credentials(): void
    {
        $user = User::factory()->create();
        $policy = app(AccountSignInMethodPolicy::class);

        self::assertSame([
            'password' => true,
            'google' => false,
            'passkeys' => 0,
            'count' => 1,
        ], $policy->summary($user));

        AccountIdentity::query()->create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_subject' => 'policy-google-subject',
            'provider_email' => 'provider@example.test',
            'provider_email_verified_at' => now(),
            'linked_at' => now(),
            'last_used_at' => now(),
        ]);
        DB::table('passkeys')->insert([
            'public_id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'name' => 'Policy passkey',
            'credential_id' => 'policy-credential-id',
            'credential' => json_encode(['test' => true], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        self::assertSame([
            'password' => true,
            'google' => true,
            'passkeys' => 1,
            'count' => 3,
        ], $policy->summary($user->refresh()));
    }

    public function test_final_password_method_cannot_be_removed(): void
    {
        $user = User::factory()->create();

        $this->expectException(ValidationException::class);
        app(RemovePassword::class)->handle((int) $user->id);
    }

    public function test_password_can_be_removed_when_google_remains(): void
    {
        $user = User::factory()->create();
        AccountIdentity::query()->create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_subject' => 'remaining-google-subject',
            'provider_email' => 'provider@example.test',
            'provider_email_verified_at' => now(),
            'linked_at' => now(),
            'last_used_at' => now(),
        ]);

        app(RemovePassword::class)->handle((int) $user->id);

        self::assertNull($user->refresh()->getRawOriginal('password'));
        self::assertTrue($user->supportsGoogleAuthentication());
    }
}
