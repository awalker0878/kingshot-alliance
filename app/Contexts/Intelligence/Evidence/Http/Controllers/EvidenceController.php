<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Http\Controllers;

use App\Contexts\GameWorld\Players\Services\PlayerContext;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Intelligence\Evidence\Actions\UploadGameEvidence;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

final class EvidenceController extends Controller
{
    public function __construct(private readonly PlayerContext $playerContext) {}

    public function store(Request $request, string $occurrence, UploadGameEvidence $upload): RedirectResponse
    {
        $request->validate(['evidence' => ['required', 'file']]);
        $actor = $this->playerContext->playerOrNull();
        abort_unless($actor instanceof PlayerReference, 409, 'Select a Player before importing battle reports.');
        $file = $request->file('evidence');
        abort_unless($file instanceof UploadedFile, 422);

        $result = $upload->handle($actor->playerId, $occurrence, $file);

        return redirect()->route('events.screenshot-intake', ['occurrence' => $occurrence])
            ->with('actionReceipt', $this->receipt('completed', [
                'evidenceId' => $result->evidenceId,
                'duplicate' => $result->duplicate ? 1 : 0,
            ]));
    }
}
