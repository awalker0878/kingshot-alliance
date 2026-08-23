<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Alliance\Content;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\v3\TestCase;

final class AllianceContentGameParityHttpContractV3Test extends TestCase
{
    public function test_first_class_rules_and_reaction_routes_are_registered(): void
    {
        $rulesRead = $this->route('alliance.rules.index');
        $rulesWrite = $this->route('alliance.rules.update');
        $reactionSet = $this->route('alliance.content.reaction.update');
        $reactionRemove = $this->route('alliance.content.reaction.destroy');

        self::assertSame(['GET', 'HEAD'], $rulesRead->methods());
        self::assertSame('alliance/rules', $rulesRead->uri());
        self::assertSame(['PUT'], $rulesWrite->methods());
        self::assertSame('alliance/rules', $rulesWrite->uri());
        self::assertSame(['PUT'], $reactionSet->methods());
        self::assertSame('alliance/content/{content}/reaction', $reactionSet->uri());
        self::assertSame(['DELETE'], $reactionRemove->methods());
        self::assertSame('alliance/content/{content}/reaction', $reactionRemove->uri());
    }

    public function test_reactions_do_not_inherit_password_confirmed_publishing_authority(): void
    {
        $reactionMiddleware = $this->route('alliance.content.reaction.update')->gatherMiddleware();
        $rulesWriteMiddleware = $this->route('alliance.rules.update')->gatherMiddleware();

        self::assertContains('alliance.context', $reactionMiddleware);
        self::assertContains('verified', $reactionMiddleware);
        self::assertNotContains('password.confirm', $reactionMiddleware);
        self::assertContains('password.confirm', $rulesWriteMiddleware);
        self::assertContains('alliance.context', $rulesWriteMiddleware);
    }

    private function route(string $name): Route
    {
        $route = RouteFacade::getRoutes()->getByName($name);
        self::assertInstanceOf(Route::class, $route);

        return $route;
    }
}
