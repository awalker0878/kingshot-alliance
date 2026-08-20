<?php

declare(strict_types=1);

namespace Tests\v3\Shared\Infrastructure;

use App\Shared\Infrastructure\Pagination\PageSlice;
use App\Shared\Infrastructure\Pagination\ScopedCursorCodec;
use Illuminate\Validation\ValidationException;
use Tests\v3\TestCase;

final class PaginationBehaviorV3Test extends TestCase
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

    public function test_page_slices_publish_one_consistent_transport_shape(): void
    {
        $page = new PageSlice(
            items: [['id' => 'candidate-a']],
            nextCursor: 'opaque-next-cursor',
            pageSize: 50,
            isFirstPage: false,
        );

        self::assertSame([
            'items' => [['id' => 'candidate-a']],
            'nextCursor' => 'opaque-next-cursor',
            'hasMore' => true,
            'pageSize' => 50,
            'isFirstPage' => false,
        ], $page->toArray());
    }
}
