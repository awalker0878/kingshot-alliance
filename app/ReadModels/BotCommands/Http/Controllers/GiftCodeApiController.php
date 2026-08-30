<?php

declare(strict_types=1);

namespace App\ReadModels\BotCommands\Http\Controllers;

use App\ReadModels\BotCommands\Queries\GiftCodeApiQuery;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class GiftCodeApiController extends Controller
{
    public function __invoke(Request $request, GiftCodeApiQuery $giftCodes): JsonResponse
    {
        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'cursor' => ['nullable', 'string', 'max:2048'],
            'status' => ['nullable', Rule::in(['active', 'pending', 'disputed', 'expired', 'history'])],
        ]);
        $status = (string) ($validated['status'] ?? 'active');
        abort_if($status !== 'active', 403, 'This credential may read verified active Gift Codes only.');

        $page = $giftCodes->page(
            (int) ($validated['limit'] ?? 25),
            isset($validated['cursor']) ? (string) $validated['cursor'] : null,
        );

        return response()->json([
            'data' => $page['items'],
            'meta' => [
                'generated_at' => now()->utc()->toIso8601String(),
                'read_only' => true,
                'status' => 'active',
                'next_cursor' => $page['nextCursor'],
                'previous_cursor' => $page['previousCursor'],
                'per_page' => $page['perPage'],
                'has_more' => $page['hasMore'],
            ],
        ]);
    }
}
