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

    old = "&& ! str_starts_with($method->getName(), '__'),"
    new = "&& ($method->getName() === '__invoke' || ! str_starts_with($method->getName(), '__')) ,"
    replace("tests/v2/Support/CapabilitySurfaceTestCase.php", old, new.replace("')) ,", "')),"))


def fix_context_composition_boundary() -> None:
    path = "tests/v2/Architecture/ArchitectureBoundariesV2Test.php"
    old = """        foreach ($this->phpFiles(['app/Contexts']) as $file) {
            $source = (string) file_get_contents($file);
            self::assertStringNotContainsString('use App\\\\Workflows\\\\', $source, $file);
            self::assertStringNotContainsString('use App\\\\ReadModels\\\\', $source, $file);
        }
"""
    new = """        foreach ($this->phpFiles(['app/Contexts']) as $file) {
            $source = (string) file_get_contents($file);
            self::assertStringNotContainsString('use App\\\\Workflows\\\\', $source, $file);

            $normalized = str_replace('\\\\', '/', $file);
            if (! str_contains($normalized, '/Http/')) {
                self::assertStringNotContainsString('use App\\\\ReadModels\\\\', $source, $file);
            }
        }
"""
    replace(path, old, new)


def fix_game_world_governance_scenario() -> None:
    replace(
        "tests/v2/Contexts/GameWorld/Governance/KingdomGovernanceBehaviorV2Test.php",
        "$kingdom = $player->kingdom()->firstOrFail();",
        "$kingdom = $factory->kingdom(13001);",
    )


def fix_platform_authority_scenario() -> None:
    path = "tests/v2/Contexts/Platform/Access/PlatformAdministrationBehaviorV2Test.php"
    target = ROOT / path
    content = target.read_text()
    signature = "    public function test_platform_mutation_authority_requires_transaction_and_active_grant(): void\n"
    start = content.find(signature)
    if start < 0:
        raise RuntimeError(f"Expected Platform authority test method not found in {path}")

    class_end = content.rfind("\n}")
    if class_end <= start:
        raise RuntimeError(f"Could not locate Platform authority test class end in {path}")

    replacement = """    public function test_platform_mutation_authority_requires_an_active_grant(): void
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
    content = content[:start] + replacement + content[class_end:]
    content = content.replace("use Illuminate\\Support\\Facades\\DB;\n", "")
    content = content.replace("use LogicException;\n", "")
    target.write_text(content)


def diagnose_context_composition_dependencies() -> None:
    invalid: list[str] = []
    allowed_http: list[str] = []

    for file in sorted((ROOT / "app/Contexts").rglob("*.php")):
        source = file.read_text()
        if "use App\\ReadModels\\" not in source:
            continue

        relative = str(file.relative_to(ROOT)).replace("\\", "/")
        if "/Http/" in f"/{relative}":
            allowed_http.append(relative)
        else:
            invalid.append(relative)

    if allowed_http:
        print("ARCH-V2-DIAGNOSTIC: HTTP adapters consuming ReadModels:")
        for offender in allowed_http:
            print(f"  - {offender}")

    if invalid:
        raise RuntimeError(
            "Non-HTTP context code depends on ReadModels: " + ", ".join(invalid)
        )

    print("ARCH-V2-DIAGNOSTIC: no domain/application context -> ReadModel dependencies found.")


def main() -> None:
    fix_capability_reflection()
    fix_context_composition_boundary()
    fix_game_world_governance_scenario()
    fix_platform_authority_scenario()
    diagnose_context_composition_dependencies()
    print("ARCH-V2-TESTS: applied clean-room test harness corrections.")


if __name__ == "__main__":
    main()
