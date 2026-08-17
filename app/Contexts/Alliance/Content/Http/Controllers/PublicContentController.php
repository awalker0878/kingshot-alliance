<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Http\Controllers;

use App\Contexts\Alliance\Content\Queries\ContentQuery;
use App\Contexts\Alliance\Content\Services\ContentPresenter;
use App\Contexts\Alliance\Lifecycle\Enums\AllianceStatus;
use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class PublicContentController extends Controller
{
    public function __invoke(
        Request $request,
        ContentQuery $content,
        ContentPresenter $presenter,
        string $slug,
        string $contentSlug,
    ): Response {
        $alliance = Alliance::query()
            ->where('slug', $slug)
            ->where('status', AllianceStatus::Active->value)
            ->firstOrFail();
        $item = $content->publicBySlug((string) $alliance->id, $contentSlug);
        abort_unless($item !== null, 404);

        return Inertia::render('Public/Content', [
            'alliance' => [
                'name' => $alliance->name,
                'slug' => $alliance->slug,
                'timezone' => $alliance->timezone,
            ],
            'content' => $presenter->item($item, true),
            'viewerTimezone' => $request->user()?->timezone,
        ]);
    }
}
