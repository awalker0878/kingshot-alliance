<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use App\Domain\Alliances\ValueObjects\TenantContextSnapshot;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class TenantContextSnapshotTest extends TestCase
{
    public function test_cache_storage_export_and_serialization_are_all_tenant_scoped(): void
    {
        $first = new TenantContextSnapshot(
            allianceId: (string) Str::ulid(),
            actorUserId: 42,
            requestId: 'request-1',
            traceId: '0123456789abcdef0123456789abcdef',
        );
        $second = new TenantContextSnapshot(allianceId: (string) Str::ulid());

        self::assertNotSame($first->cacheKey('events', 'upcoming'), $second->cacheKey('events', 'upcoming'));
        self::assertSame('alliances/'.$first->allianceId.'/media/banner.png', $first->storagePath('media/banner.png'));
        self::assertSame('alliances/'.$first->allianceId.'/exports/members.csv', $first->exportPath('members.csv'));
        self::assertSame($first->toArray(), TenantContextSnapshot::fromArray($first->toArray())->toArray());
        self::assertSame($first->allianceId, $first->logContext()['alliance_id']);
    }

    public function test_storage_and_export_helpers_reject_path_escape_attempts(): void
    {
        $context = new TenantContextSnapshot(allianceId: (string) Str::ulid());

        foreach (['../secret.txt', 'media/../secret.txt', './secret.txt', 'media\\secret.txt'] as $path) {
            try {
                $context->storagePath($path);
                self::fail('Unsafe tenant path must be rejected: '.$path);
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }
        }

        $this->expectException(InvalidArgumentException::class);
        $context->exportPath('../members.csv');
    }

    public function test_invalid_alliance_identifier_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TenantContextSnapshot(allianceId: 'not-a-ulid');
    }
}
