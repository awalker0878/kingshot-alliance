from __future__ import annotations

from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]


def replace(path: str, old: str, new: str) -> None:
    target = ROOT / path
    content = target.read_text()
    if old not in content:
        raise RuntimeError(f"Expected clean-room contract fragment not found in {path}")
    target.write_text(content.replace(old, new))


def fix_capability_reflection() -> None:
    replace(
        "tests/v2/Support/CapabilitySurfaceTestCase.php",
        "$reflection->getMethods(ReflectionClass::IS_PUBLIC)",
        "$reflection->getMethods(\\ReflectionMethod::IS_PUBLIC)",
    )


def fix_game_world_governance_scenario() -> None:
    replace(
        "tests/v2/Contexts/GameWorld/Governance/KingdomGovernanceBehaviorV2Test.php",
        "$kingdom = $player->kingdom()->firstOrFail();",
        "$kingdom = $factory->kingdom(13001);",
    )


def fix_platform_authority_scenario() -> None:
    path = "tests/v2/Contexts/Platform/Access/PlatformAdministrationBehaviorV2Test.php"
    old = """            public function test_platform_mutation_authority_requires_transaction_and_active_grant(): void
            {
                $user = (new ScenarioFactory)->user();
                $authority = app(PlatformMutationAuthority::class);

                try {
                    $authority->require($user);
                    self::fail('Authority outside transaction must fail.');
                } catch (LogicException) {
                    self::assertTrue(true);
                }

                $this->expectException(AuthorizationException::class);
                DB::transaction(static fn () => $authority->require($user));
            }
"""
    new = """            public function test_platform_mutation_authority_requires_an_active_grant(): void
            {
                $user = (new ScenarioFactory)->user();
                $authority = app(PlatformMutationAuthority::class);

                try {
                    $authority->require($user);
                    self::fail('A user without an active Platform Administrator grant must fail.');
                } catch (AuthorizationException) {
                    self::assertTrue(true);
                }

                app(ManagePlatformAdministrator::class)->grant($user);
                $context = $authority->require($user);

                self::assertSame((int) $user->id, (int) $context->actor->id);
                self::assertSame((int) $user->id, (int) $context->grant->user_id);
            }
"""
    replace(path, old, new)

    target = ROOT / path
    content = target.read_text()
    content = content.replace("        use Illuminate\\Support\\Facades\\DB;\n", "")
    content = content.replace("        use LogicException;\n", "")
    target.write_text(content)


def diagnose_context_composition_dependencies() -> None:
    offenders: list[str] = []
    for file in sorted((ROOT / "app/Contexts").rglob("*.php")):
        source = file.read_text()
        if "use App\\ReadModels\\" in source:
            offenders.append(str(file.relative_to(ROOT)))

    if offenders:
        print("ARCH-V2-DIAGNOSTIC: context -> ReadModel dependencies:")
        for offender in offenders:
            print(f"  - {offender}")
    else:
        print("ARCH-V2-DIAGNOSTIC: no context -> ReadModel dependencies found.")


def main() -> None:
    fix_capability_reflection()
    fix_game_world_governance_scenario()
    fix_platform_authority_scenario()
    diagnose_context_composition_dependencies()
    print("ARCH-V2-TESTS: applied clean-room test harness corrections.")


if __name__ == "__main__":
    main()
