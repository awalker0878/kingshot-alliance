<?php

declare(strict_types=1);

namespace Tests\Shared\Infrastructure\Pagination\Unit;

use App\Shared\Infrastructure\Pagination\PageSlice;
use PHPUnit\Framework\TestCase;

final class PageSliceTest extends TestCase
{
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
