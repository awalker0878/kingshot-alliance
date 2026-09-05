<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\Actions;

use App\Contexts\Communications\Delivery\Enums\DeliveryChannel;
use App\Contexts\Communications\Delivery\Enums\EndpointHealthStatus;
use App\Contexts\Communications\Delivery\Models\NotificationEndpoint;
use App\Contexts\Communications\Delivery\Services\EndpointConfigurationValidator;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SaveNotificationEndpoint
{
    private const MAX_ENDPOINTS_PER_GOVERNOR = 20;

    public function __construct(
        private PlayerReferenceQuery $players,
        private EndpointConfigurationValidator $validator,
        private AuditRecorder $audit,
    ) {}

    /** @param array<string,string> $configuration */
    public function handle(
        int $recipientUserId,
        string $playerId,
        DeliveryChannel $channel,
        string $label,
        array $configuration,
    ): string {
        if (! $channel->usesStoredEndpoint()) {
            throw ValidationException::withMessages(['channel' => 'Choose a configurable external delivery channel.']);
        }

        $label = trim($label);
        if ($label === '' || mb_strlen($label) > 100) {
            throw ValidationException::withMessages(['label' => 'Endpoint label is required and must be at most 100 characters.']);
        }

        $configuration = $this->validator->validate($channel, $configuration);

        return DB::transaction(function () use ($recipientUserId, $playerId, $channel, $label, $configuration): string {
            // The locked Governor row serializes endpoint mutations for this scope,
            // so the cap check remains race-safe without applying FOR UPDATE to an aggregate.
            $actor = $this->players->lockCurrent($playerId);
            if ($actor->userId !== $recipientUserId) {
                throw ValidationException::withMessages(['player' => 'The active Governor no longer belongs to this account.']);
            }

            $endpointCount = NotificationEndpoint::query()
                ->where('recipient_user_id', $recipientUserId)
                ->where('player_id', $playerId)
                ->count();
            if ($endpointCount >= self::MAX_ENDPOINTS_PER_GOVERNOR) {
                throw ValidationException::withMessages([
                    'channel' => 'This Governor already has the maximum number of notification destinations.',
                ]);
            }

            $endpoint = NotificationEndpoint::query()->create([
                'recipient_user_id' => $recipientUserId,
                'player_id' => $playerId,
                'channel' => $channel->value,
                'label' => $label,
                'configuration' => $configuration,
                'enabled' => true,
                'health_status' => EndpointHealthStatus::NeverTested->value,
                'last_verified_at' => null,
                'last_successful_delivery_at' => null,
                'last_failed_delivery_at' => null,
                'consecutive_failures' => 0,
                'last_error' => null,
            ]);

            $this->audit->record('notification.endpoint.saved', $actor, $endpoint, metadata: [
                'channel' => $channel->value,
                'label' => $label,
            ]);

            return (string) $endpoint->id;
        });
    }
}
