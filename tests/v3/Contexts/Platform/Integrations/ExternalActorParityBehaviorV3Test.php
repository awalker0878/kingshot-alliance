<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Platform\Integrations;

use App\Contexts\Operations\Events\Actions\CreateEvent;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\EventTypeScope;
use App\Contexts\Operations\Participation\Enums\EventRegistrationStatus;
use App\Contexts\Operations\Participation\Enums\EventResponseChoice;
use App\Contexts\Operations\Participation\Enums\EventResponseSource;
use App\Contexts\Operations\Participation\Models\EventRegistration;
use App\Contexts\Operations\Participation\Models\EventResponse;
use App\Contexts\Platform\Integrations\Actions\CreateApiCredential;
use App\Contexts\Platform\Integrations\Actions\IssueExternalActorPairingCode;
use App\Contexts\Platform\Integrations\Actions\RevokeExternalActorLink;
use App\Contexts\Platform\Integrations\Enums\ExternalActorProvider;
use App\Contexts\Platform\Integrations\Models\ExternalActorActionReceipt;
use App\Contexts\Platform\Integrations\Models\ExternalActorLink;
use App\Contexts\Platform\Integrations\Models\ExternalActorPairingCode;
use App\Contexts\Platform\Integrations\Services\ExternalActorIdentity;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class ExternalActorParityBehaviorV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_pairing_is_one_time_private_revocable_and_supports_idempotent_event_writes(): void
    {
        $scenarios = app(ScenarioFactory::class);
        $account = $scenarios->account();
        $player = $scenarios->player($account->userId, 64001);
        $alliance = $scenarios->alliance($player);
        $credential = app(CreateApiCredential::class)->handle(
            $alliance->allianceId,
            $player->playerId,
            'Participation bot',
            ['actor-links:write', 'event-participation:write'],
        );
        $pairing = app(IssueExternalActorPairingCode::class)->handle(
            $alliance->allianceId,
            $player->playerId,
            ExternalActorProvider::Discord,
        );
        $externalSubject = '123456789012345678';
        $claimPayload = [
            'provider' => 'discord',
            'external_subject' => $externalSubject,
            'code' => $pairing->code,
        ];

        $this->withToken($credential->token)
            ->postJson(route('api.v1.actor-links.claim'), $claimPayload)
            ->assertOk()
            ->assertJsonPath('data.linked', true)
            ->assertJsonPath('data.subject_hint', '…5678');
        $this->withToken($credential->token)
            ->postJson(route('api.v1.actor-links.claim'), $claimPayload)
            ->assertOk();

        $link = ExternalActorLink::query()->firstOrFail();
        self::assertSame(ExternalActorIdentity::subjectHash(ExternalActorProvider::Discord, $externalSubject), $link->subject_hash);
        self::assertNotSame($externalSubject, $link->subject_hash);

        $configuration = EventTypeScope::query()
            ->where('scope', EventScope::Alliance->value)
            ->whereHas('eventType', static fn ($query) => $query->where('slug', 'bear-hunt'))
            ->firstOrFail();
        $created = app(CreateEvent::class)->handle(
            actorPlayerId: $player->playerId,
            configurationId: (string) $configuration->id,
            scope: EventScope::Alliance,
            targetId: $alliance->allianceId,
            firstLocalStart: CarbonImmutable::now('UTC')->addHour(),
            title: 'External response test',
            durationMinutes: 60,
        );
        self::assertNotNull($created->firstOccurrenceId);
        $occurrenceId = (string) $created->firstOccurrenceId;
        $identity = ['provider' => 'discord', 'external_subject' => $externalSubject];

        $this->withToken($credential->token)
            ->withHeader('Idempotency-Key', 'response-command-0001')
            ->putJson(route('api.v1.me.events.response', ['occurrence' => $occurrenceId]), [
                ...$identity,
                'response' => 'going',
                'preferred_role' => 'Rally lead',
            ])
            ->assertOk()
            ->assertJsonPath('data.response', 'going')
            ->assertJsonPath('meta.replayed', false);
        $this->withToken($credential->token)
            ->withHeader('Idempotency-Key', 'response-command-0001')
            ->putJson(route('api.v1.me.events.response', ['occurrence' => $occurrenceId]), [
                ...$identity,
                'response' => 'going',
                'preferred_role' => 'Rally lead',
            ])
            ->assertOk()
            ->assertJsonPath('meta.replayed', true);

        $response = EventResponse::query()->firstOrFail();
        self::assertSame(EventResponseChoice::Going, $response->response);
        self::assertSame(EventResponseSource::External, $response->source);
        self::assertSame(1, ExternalActorActionReceipt::query()->where('action', 'event.response.update')->count());

        $this->withToken($credential->token)
            ->withHeader('Idempotency-Key', 'response-command-0001')
            ->putJson(route('api.v1.me.events.response', ['occurrence' => $occurrenceId]), [
                ...$identity,
                'response' => 'maybe',
            ])
            ->assertUnprocessable();

        $this->withToken($credential->token)
            ->withHeader('Idempotency-Key', 'registration-command-0001')
            ->putJson(route('api.v1.me.events.registration', ['occurrence' => $occurrenceId]), [
                ...$identity,
                'registered' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.registered', true);
        self::assertSame(EventRegistrationStatus::Registered, EventRegistration::query()->firstOrFail()->status);

        app(RevokeExternalActorLink::class)->handle(
            $alliance->allianceId,
            $player->playerId,
            (string) $link->id,
        );
        $this->withToken($credential->token)
            ->withHeader('Idempotency-Key', 'response-command-0002')
            ->putJson(route('api.v1.me.events.response', ['occurrence' => $occurrenceId]), [
                ...$identity,
                'response' => 'unavailable',
            ])
            ->assertNotFound();
    }

    public function test_under_scoped_credentials_and_expired_pairing_codes_are_rejected(): void
    {
        $scenarios = app(ScenarioFactory::class);
        $account = $scenarios->account();
        $player = $scenarios->player($account->userId, 64002);
        $alliance = $scenarios->alliance($player);
        $credential = app(CreateApiCredential::class)->handle(
            $alliance->allianceId,
            $player->playerId,
            'Read-only bot',
            ['events:read'],
        );

        $this->withToken($credential->token)
            ->postJson(route('api.v1.actor-links.claim'), [
                'provider' => 'telegram',
                'external_subject' => '123456789',
                'code' => 'ABCD-EFGH-JKLM',
            ])
            ->assertUnauthorized();

        $writeCredential = app(CreateApiCredential::class)->handle(
            $alliance->allianceId,
            $player->playerId,
            'Pairing bot',
            ['actor-links:write'],
        );
        $pairing = app(IssueExternalActorPairingCode::class)->handle(
            $alliance->allianceId,
            $player->playerId,
            ExternalActorProvider::Telegram,
        );
        ExternalActorPairingCode::query()->findOrFail($pairing->pairingCodeId)
            ->forceFill(['expires_at' => now()->subMinute()])
            ->save();
        $this->withToken($writeCredential->token)
            ->postJson(route('api.v1.actor-links.claim'), [
                'provider' => 'telegram',
                'external_subject' => '123456789',
                'code' => $pairing->code,
            ])
            ->assertUnprocessable();

        self::assertContains('actor-links:write', CreateApiCredential::allowedScopes());
        self::assertContains('event-participation:write', CreateApiCredential::allowedScopes());
    }
}
