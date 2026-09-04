<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\Actions;

use App\Contexts\Communications\Delivery\Enums\EndpointHealthStatus;
use App\Contexts\Communications\Delivery\Models\NotificationEndpoint;
use App\Contexts\Communications\Delivery\Services\EndpointConfigurationValidator;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class UpdateNotificationEndpoint
{
    public function __construct(
        private PlayerReferenceQuery $players,
        private EndpointConfigurationValidator $validator,
        private AuditRecorder $audit,
    ) {}

    /** @param array<string,string> $configuration */
    public function handle(
        int $recipientUserId,
        string $playerId,
        string $endpointId,
        string $label,
        array $configuration,
    ): void {
        $label = trim($label);
        if ($label === '' || mb_strlen($label) > 100) {
            throw ValidationException::withMessages(['label' => 'Endpoint label is required and must be at most 100 characters.']);
        }

        DB::transaction(function () use ($recipientUserId, $playerId, $endpointId, $label, $configuration): void {
            $actor = $this->players->lockCurrent($playerId);
            if ($actor->userId !== $recipientUserId) {
                throw ValidationException::withMessages(['player' => 'The active Governor no longer belongs to this account.']);
            }

            $endpoint = NotificationEndpoint::query()
                ->whereKey($endpointId)
                ->where('recipient_user_id', $recipientUserId)
                ->where('player_id', $playerId)
                ->lockForUpdate()
                ->firstOrFail();

            $validated = $this->validator->validate($endpoint->channel, $configuration);
            $endpoint->forceFill([
                'label' => $label,
                'configuration' => $validated,
                'enabled' => true,
                'health_status' => EndpointHealthStatus::NeverTested,
                'last_verified_at' => null,
                'consecutive_failures' => 0,
                'last_error' => null,
            ])->save();

            $this->audit->record('notification.endpoint.updated', $actor, $endpoint, metadata: [
                'channel' => $endpoint->channel->value,
                'label' => $label,
            ]);
        });
    }
}
