<?php

declare(strict_types=1);

namespace App\ReadModels\RecruitmentDiscovery\Http\Controllers;

use App\ReadModels\RecruitmentDiscovery\Queries\PublicRecruitmentBoardQuery;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class PublicRecruitmentBoardController extends Controller
{
    public function __invoke(Request $request, PublicRecruitmentBoardQuery $board): Response
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'kingdom' => ['nullable', 'integer', 'min:1'],
            'language' => ['nullable', 'string', 'max:16'],
        ]);

        return Inertia::render('Public/Recruitment/Index', $board->search(
            $validated['q'] ?? null,
            isset($validated['kingdom']) ? (int) $validated['kingdom'] : null,
            $validated['language'] ?? null,
        ));
    }
}
