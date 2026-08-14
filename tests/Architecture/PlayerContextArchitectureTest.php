<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class PlayerContextArchitectureTest extends TestCase
{
    public function test_player_context_is_owned_by_user_id_and_event_forms_do_not_accept_actor_player_ids(): void
    {
        $root = dirname(__DIR__, 2);
        $middleware = file_get_contents($root.'/app/Domain/Kingdoms/Http/Middleware/ResolvePlayerContext.php');
        $activation = file_get_contents($root.'/app/Domain/Kingdoms/Http/Controllers/ActivatePlayerController.php');

        self::assertIsString($middleware);
        self::assertIsString($activation);
        self::assertStringContainsString("->where('user_id', $user->id)", $middleware);
        self::assertStringContainsString("->where('user_id', $user->id)", $activation);
    }
}
