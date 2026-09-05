<?php

declare(strict_types=1);

namespace Tests\v3\Fixtures;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\GameWorld\GiftCodes\Actions\ReconcileGiftCodeStatus;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeEvidenceClassification;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeEvidenceVerificationState;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeSource;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeStatus;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCode;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeProvenance;
use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\Platform\Administration\Actions\ManagePlatformAdministrator;
use Illuminate\Support\Facades\Hash;

final class GiftCodeVisualFixture
{
    public static function seed(): void
    {
        $catalogueUser = User::factory()->create([
            'name' => 'Gift Code Catalogue Visual',
            'email' => 'gift-code-catalogue-visual@example.test',
            'password' => Hash::make('password'),
            'timezone' => 'UTC',
        ]);
        $kingdoms = [
            Kingdom::query()->create(['number' => 1623, 'status' => 'active']),
            Kingdom::query()->create(['number' => 1624, 'status' => 'active']),
        ];
        foreach ($kingdoms as $index => $kingdom) {
            Player::query()->create([
                'user_id' => $catalogueUser->id,
                'current_kingdom_id' => $kingdom->id,
                'game_player_id' => sprintf('GOV-GIFT-%d', $index + 1),
                'current_name' => sprintf('Gift Code Governor %d', $index + 1),
            ]);
        }
        foreach ([
            'VISUAL-GIFT-DESKTOP',
            'VISUAL-GIFT-MOBILE',
            'VISUAL-GIFT-WORKSPACE-DESKTOP',
            'VISUAL-GIFT-WORKSPACE-MOBILE',
        ] as $code) {
            self::createValidGiftCode($code);
        }

        foreach ([
            'desktop' => ['kingdom' => 1625, 'suffix' => 'DESKTOP'],
            'mobile' => ['kingdom' => 1626, 'suffix' => 'MOBILE'],
        ] as $project => $fixture) {
            $trustUser = User::factory()->create([
                'name' => 'Gift Code Trust '.ucfirst($project).' Visual',
                'email' => sprintf('gift-code-trust-%s-visual@example.test', $project),
                'password' => Hash::make('password'),
                'timezone' => 'UTC',
            ]);
            $trustKingdom = Kingdom::query()->create([
                'number' => $fixture['kingdom'],
                'status' => 'active',
            ]);
            Player::query()->create([
                'user_id' => $trustUser->id,
                'current_kingdom_id' => $trustKingdom->id,
                'game_player_id' => 'GOV-GIFT-TRUST-'.$fixture['suffix'],
                'current_name' => 'Gift Code Trust '.$fixture['suffix'].' Governor',
            ]);
            self::createValidGiftCode('VISUAL-GIFT-TRUST-'.$fixture['suffix']);
        }

        $user = User::factory()->create([
            'name' => 'Gift Code Moderation Visual',
            'email' => 'gift-code-moderation-visual@example.test',
            'password' => Hash::make('password'),
            'timezone' => 'UTC',
            'email_verified_at' => now(),
            'two_factor_confirmed_at' => now(),
        ]);
        app(ManagePlatformAdministrator::class)->grant((int) $user->id);
    }

    private static function createValidGiftCode(string $code): void
    {
        $giftCode = GiftCode::query()->create([
            'code' => $code,
            'normalized_code' => $code,
            'status' => GiftCodeStatus::Pending,
            'status_revision' => 0,
            'status_reason_code' => 'awaiting_verified_evidence',
            'status_evidence_ids' => [],
            'status_changed_at' => now(),
            'status_derived_at' => now(),
            'discovered_at' => now(),
            'expires_revision' => 0,
        ]);

        foreach (['alpha', 'beta'] as $source) {
            GiftCodeProvenance::query()->create([
                'gift_code_id' => (string) $giftCode->id,
                'source_type' => GiftCodeSource::Community,
                'source_label' => 'Visual verified observation '.$source,
                'assertion' => 'available',
                'evidence_classification' => GiftCodeEvidenceClassification::IndependentObservation,
                'verification_state' => GiftCodeEvidenceVerificationState::Verified,
                'observed_at' => now(),
                'fingerprint' => hash('sha256', $code.'|'.$source),
            ]);
        }

        app(ReconcileGiftCodeStatus::class)->handle((string) $giftCode->id);
    }
}
