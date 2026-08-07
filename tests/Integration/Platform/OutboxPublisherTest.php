<?php

declare(strict_types=1);

namespace Tests\Integration\Platform;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Actions\PublishOutboxBatch;
use App\Domain\Platform\Events\OutboxPublished;
use App\Domain\Platform\Models\OutboxMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

final class OutboxPublisherTest extends TestCase
{
    use RefreshDatabase;

    public function test_eligible_message_is_published_once_with_idempotency_and_tenant_context(): void
    {
        Event::fake([OutboxPublished::class]);

        $creator = User::factory()->create();
        $alliance = Alliance::query()->create([
            'name' => 'Outbox Test Alliance',
            'slug' => 'outbox-test-alliance',
            'created_by_user_id' => $creator->id,
        ]);
        $message = $this->message([
            'alliance_id' => $alliance->id,
            'idempotency_key' => 'membership.changed:'.$alliance->id,
        ]);

        $published = $this->app->make(PublishOutboxBatch::class)->handle();

        self::assertSame(1, $published);
        $message->refresh();
        self::assertSame(1, $message->attempts);
        self::assertNotNull($message->published_at);
        self::assertNull($message->last_error);

        Event::assertDispatched(
            OutboxPublished::class,
            static fn (OutboxPublished $event): bool => $event->messageId === $message->id
                && $event->allianceId === $message->alliance_id
                && $event->idempotencyKey === $message->idempotency_key
                && $event->payload === $message->payload,
        );

        Event::fake([OutboxPublished::class]);
        self::assertSame(0, $this->app->make(PublishOutboxBatch::class)->handle());
        Event::assertNotDispatched(OutboxPublished::class);
    }

    public function test_failed_publication_is_released_for_bounded_retry_without_being_marked_published(): void
    {
        Event::listen(
            OutboxPublished::class,
            static function (): never {
                throw new RuntimeException('downstream unavailable');
            },
        );

        $message = $this->message();

        $published = $this->app->make(PublishOutboxBatch::class)->handle(1);

        self::assertSame(0, $published);
        $message->refresh();
        self::assertSame(1, $message->attempts);
        self::assertNull($message->published_at);
        self::assertSame('downstream unavailable', $message->last_error);
        self::assertTrue($message->available_at->isFuture());
    }

    public function test_future_message_is_not_claimed_before_available_time(): void
    {
        Event::fake([OutboxPublished::class]);
        $message = $this->message([
            'available_at' => now()->addHour(),
        ]);

        self::assertSame(0, $this->app->make(PublishOutboxBatch::class)->handle());
        self::assertSame(0, $message->refresh()->attempts);
        Event::assertNotDispatched(OutboxPublished::class);
    }

    /** @param array<string, mixed> $overrides */
    private function message(array $overrides = []): OutboxMessage
    {
        return OutboxMessage::query()->create(array_merge([
            'alliance_id' => null,
            'event_type' => 'test.event',
            'aggregate_type' => 'test.aggregate',
            'aggregate_id' => 'aggregate-1',
            'idempotency_key' => 'test.event:aggregate-1',
            'payload' => ['value' => 1],
            'occurred_at' => now(),
            'available_at' => now(),
            'attempts' => 0,
        ], $overrides));
    }
}
