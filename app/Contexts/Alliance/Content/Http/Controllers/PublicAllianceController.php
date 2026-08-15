<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Http\Controllers;

use App\Contexts\Alliance\Content\Enums\ContentStatus;
use App\Contexts\Alliance\Content\Enums\ContentVisibility;
use App\Contexts\Alliance\Content\Models\AllianceBrandingMedia;
use App\Contexts\Alliance\Content\Models\AllianceProfile;
use App\Contexts\Alliance\Content\Models\ContentCategory;
use App\Contexts\Alliance\Content\Models\ContentItem;
use App\Contexts\Alliance\Content\Queries\ContentQuery;
use App\Contexts\Alliance\Content\Services\ContentPresenter;
use App\Contexts\Alliance\Core\Enums\AllianceStatus;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Alliance\Recruitment\Queries\PublicRecruitmentQuery;
use App\Shared\Http\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class PublicAllianceController extends Controller
{
    public function __invoke(
        Request $request,
        ContentQuery $content,
        ContentPresenter $presenter,
        PublicRecruitmentQuery $recruitment,
        string $slug,
    ): Response {
        $alliance = Alliance::query()
            ->with('kingdom')
            ->where('slug', $slug)
            ->where('status', AllianceStatus::Active->value)
            ->firstOrFail();

        $profile = AllianceProfile::query()->where('alliance_id', $alliance->id)->first();
        $publicRecruitment = $recruitment->forAlliance($alliance);
        $items = $content->publicList(
            $alliance,
            $request->string('q')->toString(),
            $request->string('type')->toString(),
            $request->string('category')->toString(),
            $request->string('locale')->toString(),
        );

        $brandingSlots = AllianceBrandingMedia::query()
            ->where('alliance_id', $alliance->id)
            ->pluck('media_id', 'slot');

        $categories = ContentCategory::query()
            ->where('alliance_id', $alliance->id)
            ->whereHas('items', static fn ($query) => $query
                ->where('status', ContentStatus::Published->value)
                ->where('visibility', ContentVisibility::Public->value)
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->whereNull('archived_at'))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        /** @var list<array{name: string, slug: string}> $categoryData */
        $categoryData = [];
        foreach ($categories as $category) {
            $categoryData[] = [
                'name' => (string) $category->name,
                'slug' => (string) $category->slug,
            ];
        }

        /** @var list<array<string, mixed>> $contentData */
        $contentData = [];
        foreach ($items as $item) {
            if ($item instanceof ContentItem) {
                $contentData[] = $presenter->item($item);
            }
        }

        return Inertia::render('Public/Alliance', [
            'alliance' => [
                'name' => $alliance->name,
                'slug' => $alliance->slug,
                'kingdom' => $alliance->kingdom === null ? null : (string) $alliance->kingdom->number,
                'language' => $alliance->language,
                'timezone' => $alliance->timezone,
                'description' => $profile?->description,
                'recruitmentStatus' => $publicRecruitment['status'],
                'primaryColor' => $profile?->primary_color,
                'logoUrl' => $brandingSlots->has('logo') ? route('public.alliances.branding', [$alliance->slug, 'logo']) : null,
                'bannerUrl' => $brandingSlots->has('banner') ? route('public.alliances.branding', [$alliance->slug, 'banner']) : null,
                'recruitmentApplicationUrl' => $publicRecruitment['application_url'],
            ],
            'filters' => [
                'q' => $request->string('q')->toString(),
                'type' => $request->string('type')->toString(),
                'category' => $request->string('category')->toString(),
                'locale' => $request->string('locale')->toString(),
            ],
            'categories' => $categoryData,
            'content' => $contentData,
        ]);
    }
}
