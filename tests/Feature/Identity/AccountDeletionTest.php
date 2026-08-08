<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Identity\Actions\RequestAccountDeletion;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Actions\ProcessAccountDeletionRequests;
use App\Domain\Platform\Services\LegalHoldService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_alliance_owner_must_transfer_ownership_before_requesting_deletion(): void
    {
        $owner = User::factory()->create();
        $this->app->make(CreateAlliance::class)->handle($owner, 'Deletion Owner', 'deletion-owner');

        $this->expectException(ValidationException::class);
        $this->app->make(RequestAccountDeletion::class)->handle($owner);
    }

    public function test_eligible_request_anonymizes_account_and_preserves_membership_history(): void
    {
        $user = User::factory()->create(['email' => 'delete-me@example.com']);
        $deletion = $this->app->make(RequestAccountDeletion::class)->handle($user);
        $deletion->forceFill(['eligible_at' => now()->subMinute()])->save();

        self::assertSame(1, $this->app->make(ProcessAccountDeletionRequests::class)->handle());

        $user->refresh();
        self::assertSame('Deleted User', $user->name);
        self::assertStringStartsWith('deleted+', $user->email);
        self::assertNotNull($user->anonymized_at);
        self::assertSame('processed', $deletion->refresh()->status);
    }

    public function test_legal_hold_blocks_processing_until_released(): void
    {
        $administrator = User::factory()->create();
        $user = User::factory()->create();
        $deletion = $this->app->make(RequestAccountDeletion::class)->handle($user);
        $deletion->forceFill(['eligible_at' => now()->subMinute()])->save();
        $hold = $this->app->make(LegalHoldService::class)->place(
            $administrator,
            'user',
            (string) $user->id,
            'Preserve account records',
        );

        self::assertSame(0, $this->app->make(ProcessAccountDeletionRequests::class)->handle());
        self::assertSame('blocked', $deletion->refresh()->status);
        self::assertNull($user->refresh()->anonymized_at);

        $this->app->make(LegalHoldService::class)->release($administrator, $hold);
        self::assertSame(1, $this->app->make(ProcessAccountDeletionRequests::class)->handle());
        self::assertNotNull($user->refresh()->anonymized_at);
    }
}
