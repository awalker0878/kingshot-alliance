<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Operations\Events;

use App\Contexts\Operations\Events\Enums\EventProfileState;
use App\Contexts\Operations\Events\Enums\EventTypeVerificationState;
use App\Contexts\Operations\Events\Enums\EventWorkflowDimension;
use App\Contexts\Operations\Events\Models\EventType;
use App\Contexts\Operations\Events\Models\EventTypeWorkflowDimension;
use App\Contexts\Operations\Events\Services\EventTypeProfileResolver;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\v3\TestCase;

final class EventTypeProfileResolverV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_bear_hunt_resolves_only_the_reviewed_workflow_dimensions(): void
    {
        $type = EventType::query()->where('slug', 'bear-hunt')->firstOrFail();

        $profile = app(EventTypeProfileResolver::class)->resolve($type);

        self::assertTrue($profile['profile_enabled']);
        self::assertSame('verified', $profile['verification_state']);
        self::assertSame([
            'debrief',
            'participation',
            'rallies',
            'readiness_closeout',
            'results',
            'roster',
            'screenshot_evidence',
        ], $profile['workflow_dimensions']);
        self::assertNotNull($profile['source']);
    }

    public function test_candidate_profile_stays_disabled_even_if_a_dimension_row_exists(): void
    {
        $type = EventType::query()->where('slug', 'alliance-mobilization')->firstOrFail();
        EventTypeWorkflowDimension::query()->create([
            'event_type_id' => $type->id,
            'dimension' => EventWorkflowDimension::Results,
        ]);

        $profile = app(EventTypeProfileResolver::class)->resolve($type->fresh());

        self::assertFalse($profile['profile_enabled']);
        self::assertSame('candidate', $profile['verification_state']);
        self::assertSame('disabled', $profile['profile_state']);
        self::assertSame([], $profile['workflow_dimensions']);
    }

    public function test_unverified_identity_cannot_be_saved_with_an_enabled_profile(): void
    {
        $type = EventType::query()->where('slug', 'alliance-brawl')->firstOrFail();

        $this->expectException(DomainException::class);

        $type->forceFill([
            'verification_state' => EventTypeVerificationState::Candidate,
            'profile_state' => EventProfileState::Enabled,
            'source_label' => 'Unreviewed source',
            'source_reference' => 'unreviewed',
            'source_observed_at' => now(),
        ])->save();
    }
}
