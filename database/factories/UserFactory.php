<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Contexts\Accounts\Identity\Enums\AuthenticationType;
use App\Contexts\Accounts\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
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
            'authentication_type' => AuthenticationType::Password,
            'password' => self::$password ??= Hash::make('password'),
            'timezone' => 'UTC',
            'remember_token' => Str::random(10),
        ];
    }

    public function google(): static
    {
        return $this->state(fn (): array => [
            'authentication_type' => AuthenticationType::Google,
            'password' => null,
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn (): array => [
            'email_verified_at' => null,
        ]);
    }
}
