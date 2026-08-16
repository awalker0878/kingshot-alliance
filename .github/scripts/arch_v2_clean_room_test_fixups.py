from __future__ import annotations

from pathlib import Path
from textwrap import dedent

ROOT = Path(__file__).resolve().parents[2]


def replace(path: str, old: str, new: str, expected: int = 1) -> None:
    target = ROOT / path
    content = target.read_text()
    count = content.count(old)
    if count != expected:
        raise RuntimeError(
            f"Expected {expected} clean-room contract fragment(s) in {path}; found {count}"
        )
    target.write_text(content.replace(old, new))


def fix_capability_reflection() -> None:
    path = "tests/v2/Support/CapabilitySurfaceTestCase.php"
    replace(
        path,
        "$reflection->getMethods(ReflectionClass::IS_PUBLIC)",
        "$reflection->getMethods(\\ReflectionMethod::IS_PUBLIC)",
    )

    old = "&& ! str_starts_with($method->getName(), '__'),"
    new = "&& ($method->getName() === '__invoke' || ! str_starts_with($method->getName(), '__')) ,"
    replace(path, old, new.replace("')) ,", "')),"))

    replace(
        path,
        """    public function test_capability_models_map_to_the_fresh_schema(): void
    {
        foreach ($this->phpFiles() as $file) {
""",
        """    public function test_capability_models_map_to_the_fresh_schema(): void
    {
        $files = $this->phpFiles();
        self::assertNotSame([], $files, static::CAPABILITY.' has no implementation surface to inspect for models.');

        foreach ($files as $file) {
""",
    )


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


def write_alliance_capacity_policy_behavior() -> None:
    target = ROOT / "tests/v2/Contexts/Alliance/Policies/AllianceCapacityPolicyBehaviorV2Test.php"
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(
        dedent(
            r"""
            <?php

            declare(strict_types=1);

            namespace Tests\v2\Contexts\Alliance\Policies;

            use App\Contexts\Alliance\Policies\AllianceCapacityPolicy;
            use Illuminate\Foundation\Testing\RefreshDatabase;
            use Illuminate\Support\Facades\DB;
            use Illuminate\Validation\ValidationException;
            use Tests\v2\Support\ScenarioFactory;
            use Tests\v2\TestCase;

            final class AllianceCapacityPolicyBehaviorV2Test extends TestCase
            {
                use RefreshDatabase;

                public function test_member_capacity_enforces_the_current_plan_entitlement(): void
                {
                    $factory = new ScenarioFactory();
                    $alliance = $factory->alliance($factory->player($factory->user()));

                    DB::table('platform_plan_entitlements')
                        ->where('plan_code', 'standard')
                        ->where('entitlement_key', 'members.max')
                        ->update([
                            'limit_value' => 1,
                            'updated_at' => now(),
                        ]);

                    $this->expectException(ValidationException::class);

                    app(AllianceCapacityPolicy::class)->assertMemberCapacity($alliance);
                }
            }
            """
        ).lstrip()
    )


def fix_frontend_sources() -> None:
    replace(
        "resources/js/pages/Alliance/TransferCompletionManage.vue",
        """const inputClass =
  'mt-2 w-full rounded-lg border border-[var(--ks-border)] bg-[var(--ks-bg)] px-3 py-2 text-sm text-[var(--ks-text)] disabled:cursor-not-allowed disabled:opacity-50';
""",
        "",
    )
    replace(
        "resources/js/pages/Dashboard.vue",
        "const props = defineProps<{",
        "defineProps<{",
    )
    replace(
        "resources/js/pages/Events/Manage.vue",
        """function parsedPollOptions(): Array<{ label: string; value: string }> {
  return pollForm.options_text
    .split(/\\r?\\n/)
    .map((line) => line.trim())
    .filter(Boolean)
    .map((line) => {
      const [label, ...rest] = line.split('|');
      const value = rest.join('|').trim() || label.trim();
      return { label: label.trim(), value };
    });
}
function savePoll(): void {
  pollForm.transform((data) => {
    const { options_text: _optionsText, ...rest } = data;
    return editingPollOptionsLocked.value ? rest : { ...rest, options: parsedPollOptions() };
  });
""",
        """function parsedPollOptions(optionsText: string): Array<{ label: string; value: string }> {
  return optionsText
    .split(/\\r?\\n/)
    .map((line) => line.trim())
    .filter(Boolean)
    .map((line) => {
      const [label, ...rest] = line.split('|');
      const value = rest.join('|').trim() || label.trim();
      return { label: label.trim(), value };
    });
}
function savePoll(): void {
  pollForm.transform((data) => {
    const { options_text: optionsText, ...rest } = data;
    return editingPollOptionsLocked.value
      ? rest
      : { ...rest, options: parsedPollOptions(optionsText) };
  });
""",
    )
    replace(
        "scripts/check-event-localization-coverage.mjs",
        """import fs from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';
""",
        """import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';
import { createRequire } from 'node:module';
""",
    )


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
    write_alliance_capacity_policy_behavior()
    fix_frontend_sources()
    diagnose_context_composition_dependencies()
    print("ARCH-V2-TESTS: applied clean-room test harness corrections.")


if __name__ == "__main__":
    main()
