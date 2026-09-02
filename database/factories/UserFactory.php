<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Contexts\Accounts\Identity\Models\AccountIdentity;
use App\Contexts\Accounts\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** @extends Factory<User> */
final class UserFactory extends Factory
{
    /** @var class-string<User> */
    protected $model = User::class;

    private static ?string $password = null;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => self::$password ??= Hash::make('password'),
            'timezone' => 'UTC',
            'remember_token' => Str::random(10),
        ];
    }

    public function withoutPassword(): static
    {
        return $this->state(fn (): array => ['password' => null]);
    }

    public function google(): static
    {
        return $this->withoutPassword()->afterCreating(static function (User $user): void {
            AccountIdentity::query()->create([
                'user_id' => $user->id,
                'provider' => 'google',
                'provider_subject' => 'google-'.Str::uuid(),
                'provider_email' => $user->email,
                'provider_email_verified_at' => now(),
                'linked_at' => now(),
                'last_used_at' => now(),
            ]);
        });
    }

    public function unverified(): static
    {
        return $this->state(fn (): array => ['email_verified_at' => null]);
    }
}
