<?php

declare(strict_types=1);

namespace App\ReadModels\AllianceDashboard\Http\Controllers;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\ReadModels\AllianceDashboard\CommandOverviewQuery;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class AllianceOverviewController extends Controller
{
    public function __invoke(
        Request $request,
        AllianceContext $context,
        CommandOverviewQuery $overview,
    ): Response {
        abort_unless($request->user() instanceof User, 401);

        $payload = $overview->forScope($context->scope());
        $invitationManagement = $payload['invitationManagement'];
        if (is_array($invitationManagement)) {
            $invitationManagement['issuedLink'] = $request->session()->get('invitationLink');
            $payload['invitationManagement'] = $invitationManagement;
        }

        return Inertia::render('Alliance/Hall', $payload);
    }
}
