<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Content\ContentPresenter;
use App\Application\Content\ContentQuery;
use App\Domain\Identity\Enums\AllianceStatus;
use App\Models\Alliance;
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
        $item = $content->publicBySlug($alliance, $contentSlug);
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
