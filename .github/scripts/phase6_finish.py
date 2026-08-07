from pathlib import Path


def replace(path: str, old: str, new: str) -> None:
    file = Path(path)
    text = file.read_text()
    if old not in text:
        raise SystemExit(f"missing anchor in {path}: {old[:80]!r}")
    file.write_text(text.replace(old, new, 1))


# PostgreSQL ignores physical column placement; keep the canonical migration portable.
migration = Path('database/migrations/2026_08_07_050000_create_platform_scale_and_administration_tables.php')
text = migration.read_text()
for suffix in [
    "->after('status')", "->after('suspended_at')", "->after('closed_at')", "->after('deleted_at')",
    "->after('restored_at')", "->after('retention_until')", "->after('two_factor_confirmed_at')",
    "->after('deletion_requested_at')",
]:
    text = text.replace(suffix, '')
migration.write_text(text)

replace(
    'app/Domain/Alliances/Http/Controllers/AllianceOverviewController.php',
    "$canManageRecruitment = $authorization->allows($user, $alliance, PermissionKey::RecruitmentManage);",
    "$canManageRecruitment = $authorization->allows($user, $alliance, PermissionKey::RecruitmentManage);\n        $canManageIntegrations = $authorization->allows($user, $alliance, PermissionKey::AllianceManage);",
)
replace(
    'app/Domain/Alliances/Http/Controllers/AllianceOverviewController.php',
    "'canManageRecruitment' => $canManageRecruitment,",
    "'canManageRecruitment' => $canManageRecruitment,\n                'canManageIntegrations' => $canManageIntegrations,",
)
replace(
    'resources/js/pages/Alliance/Overview.vue',
    '    canManageRecruitment: boolean;\n',
    '    canManageRecruitment: boolean;\n    canManageIntegrations: boolean;\n',
)
replace(
    'resources/js/pages/Alliance/Overview.vue',
    '        <Link\n          v-if="contentHub.canManageRecruitment"',
    '        <Link\n          v-if="contentHub.canManageIntegrations"\n          class="text-cyan-300 hover:text-cyan-200"\n          href="/alliance/integrations"\n          >Integrations</Link\n        >\n        <Link\n          v-if="contentHub.canManageRecruitment"',
)
replace(
    'resources/js/pages/Profile.vue',
    '      <button\n        class="rounded-lg border border-slate-700 px-4 py-2 text-sm font-semibold"',
    '      <div class="flex flex-wrap items-center gap-3">\n        <Link class="rounded-lg border border-slate-700 px-4 py-2 text-sm font-semibold" href="/profile/delete-account">\n          Account deletion\n        </Link>\n        <button\n        class="rounded-lg border border-slate-700 px-4 py-2 text-sm font-semibold"',
)
replace(
    'resources/js/pages/Profile.vue',
    '      </button>\n    </header>',
    '        </button>\n      </div>\n    </header>',
)

integration = Path('resources/js/pages/Alliance/Integrations/Manage.vue')
text = integration.read_text()
text = text.replace("import { Head, Link, router, useForm } from '@inertiajs/vue3';", "import { Head, Link, router, useForm } from '@inertiajs/vue3';\nimport { ref } from 'vue';")
text = text.replace("const webhookEventsText = defineModel<string>('webhookEventsText', { default: 'alliance.created' });", "const webhookEventsText = ref('alliance.created');")
integration.write_text(text)

# Eloquent findOrFail has a broad generic return in static analysis; narrow explicitly in lifecycle code.
replace(
    'app/Domain/Platform/Actions/ManageAllianceLifecycle.php',
    "$locked = Alliance::query()->lockForUpdate()->findOrFail($alliance->id);\n            $previous = $locked->status;",
    "$locked = Alliance::query()->whereKey($alliance->id)->lockForUpdate()->first();\n            if (! $locked instanceof Alliance) {\n                throw new InvalidArgumentException('Alliance no longer exists.');\n            }\n            $previous = $locked->status;",
)
