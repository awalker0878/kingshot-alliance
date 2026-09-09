<?php

declare(strict_types=1);

namespace Tests\Feature\Shared\Infrastructure\Pagination;

use App\Shared\Infrastructure\Pagination\ScopedCursorCodec;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class ScopedCursorCodecTest extends TestCase
{
    public function test_cursor_payloads_are_opaque_and_bound_to_their_view_scope(): void
    {
        $codec = app(ScopedCursorCodec::class);
        $cursor = $codec->encode('alliance:a:recruitment', [
            'submitted_at' => '2026-08-20T12:00:00.000000Z',
            'id' => '01K00000000000000000000000',
        ]);

        self::assertStringNotContainsString('submitted_at', $cursor);
        self::assertSame(
            [
                'submitted_at' => '2026-08-20T12:00:00.000000Z',
                'id' => '01K00000000000000000000000',
            ],
            $codec->decode($cursor, 'alliance:a:recruitment'),
        );

        $this->expectException(ValidationException::class);
        $codec->decode($cursor, 'alliance:b:recruitment');
    }
}
