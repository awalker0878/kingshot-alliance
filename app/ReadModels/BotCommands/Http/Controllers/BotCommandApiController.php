<?php

declare(strict_types=1);

namespace App\ReadModels\BotCommands\Http\Controllers;

use App\Contexts\Alliance\Content\Enums\ContentType;
use App\ReadModels\BotCommands\Queries\AllianceCommandFeedQuery;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class BotCommandApiController extends Controller
{
    public function overview(Request $request, AllianceCommandFeedQuery $commands): JsonResponse
    {
        return $this->response($commands->overview($this->allianceId($request)));
    }

    public function knowledge(Request $request, AllianceCommandFeedQuery $commands): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'type' => ['nullable', Rule::enum(ContentType::class)],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $type = isset($validated['type'])
            ? ContentType::from((string) $validated['type'])
            : null;

        return $this->response($commands->knowledge(
            $this->allianceId($request),
            isset($validated['q']) ? (string) $validated['q'] : null,
            $type,
            (int) ($validated['limit'] ?? 20),
        ));
    }

    private function allianceId(Request $request): string
    {
        $allianceId = $request->attributes->get('alliance_id');
        abort_unless(is_string($allianceId) && $allianceId !== '', 500, 'API tenant context is missing.');

        return $allianceId;
    }

    private function response(mixed $data): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => [
                'generated_at' => now()->utc()->toIso8601String(),
                'read_only' => true,
            ],
        ]);
    }
}
