from __future__ import annotations

from pathlib import Path
import re
import shutil

root = Path('.')
moves: dict[str, str] = {}


def php_namespace(text: str) -> str:
    match = re.search(r'^namespace\s+([^;]+);', text, re.M)
    if not match:
        raise RuntimeError('Namespace not found')
    return match.group(1)


def move_php(old: str, new: str, namespace: str) -> None:
    source = Path(old)
    target = Path(new)
    if not source.exists():
        raise RuntimeError(f'Missing source {old}')
    text = source.read_text()
    old_namespace = php_namespace(text)
    old_fqcn = f'{old_namespace}\\{source.stem}'
    new_fqcn = f'{namespace}\\{source.stem}'
    text = re.sub(r'^namespace\s+[^;]+;', lambda _: f'namespace {namespace};', text, count=1, flags=re.M)
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(text)
    source.unlink()
    moves[old_fqcn] = new_fqcn


# Persistence ownership.
model_domains = {
    'Alliance': 'Alliances',
    'AllianceBrandingMedia': 'Content',
    'AllianceMembership': 'Memberships',
    'AllianceProfile': 'Content',
    'AuditEvent': 'Audit',
    'ContentCategory': 'Content',
    'ContentItem': 'Content',
    'ContentRevision': 'Content',
    'Event': 'Events',
    'EventOccurrence': 'Events',
    'EventRecommendedFormation': 'Rallies',
    'EventRegistration': 'Events',
    'EventReminderDelivery': 'Notifications',
    'EventReminderRule': 'Notifications',
    'EventTemplate': 'Events',
    'Invitation': 'Memberships',
    'MediaAsset': 'Content',
    'MemberFormation': 'Rallies',
    'OutboxMessage': 'Platform',
    'Permission': 'Authorization',
    'RallyAssignment': 'Rallies',
    'RallyGroup': 'Rallies',
    'RallyGuidanceRule': 'Rallies',
    'Role': 'Authorization',
    'User': 'Identity',
}
for source in sorted(Path('app/Models').glob('*.php')):
    name = source.stem
    domain = model_domains.get(name)
    if domain is None and name.startswith('Recruitment'):
        domain = 'Recruitment'
    if domain is None:
        raise RuntimeError(f'Unclassified model {name}')
    move_php(str(source), f'app/Domain/{domain}/Models/{name}.php', f'App\\Domain\\{domain}\\Models')


# Existing primitives that have drifted into the wrong domain.
primitive_moves = [
    ('app/Domain/Identity/Authorization/DefaultAllianceRole.php', 'app/Domain/Authorization/Enums/DefaultAllianceRole.php', 'App\\Domain\\Authorization\\Enums'),
    ('app/Domain/Identity/Authorization/PermissionKey.php', 'app/Domain/Authorization/Enums/PermissionKey.php', 'App\\Domain\\Authorization\\Enums'),
    ('app/Domain/Identity/Enums/AllianceStatus.php', 'app/Domain/Alliances/Enums/AllianceStatus.php', 'App\\Domain\\Alliances\\Enums'),
    ('app/Domain/Identity/Enums/InvitationStatus.php', 'app/Domain/Memberships/Enums/InvitationStatus.php', 'App\\Domain\\Memberships\\Enums'),
    ('app/Domain/Identity/Enums/MembershipStatus.php', 'app/Domain/Memberships/Enums/MembershipStatus.php', 'App\\Domain\\Memberships\\Enums'),
    ('app/Domain/Events/Enums/RallyAssignmentRole.php', 'app/Domain/Rallies/Enums/RallyAssignmentRole.php', 'App\\Domain\\Rallies\\Enums'),
    ('app/Domain/Events/Enums/RallyAssignmentStatus.php', 'app/Domain/Rallies/Enums/RallyAssignmentStatus.php', 'App\\Domain\\Rallies\\Enums'),
    ('app/Domain/Events/Enums/EventReminderDeliveryStatus.php', 'app/Domain/Notifications/Enums/EventReminderDeliveryStatus.php', 'App\\Domain\\Notifications\\Enums'),
    ('app/Domain/Events/FormationComposition.php', 'app/Domain/Rallies/ValueObjects/FormationComposition.php', 'App\\Domain\\Rallies\\ValueObjects'),
    ('app/Domain/Shared/Events/OutboxPublished.php', 'app/Domain/Platform/Events/OutboxPublished.php', 'App\\Domain\\Platform\\Events'),
    ('app/Domain/Shared/Tenancy/TenantContextSnapshot.php', 'app/Domain/Alliances/ValueObjects/TenantContextSnapshot.php', 'App\\Domain\\Alliances\\ValueObjects'),
]
for old, new, namespace in primitive_moves:
    move_php(old, new, namespace)


# Content use cases.
content_services = {'BasicMediaScanner', 'ContentOutbox', 'ContentPresenter', 'ContentRevisionWriter', 'ContentSanitizer', 'MediaScanner'}
content_values = {'MediaScanResult'}
for source in sorted(Path('app/Application/Content').glob('*.php')):
    name = source.stem
    if name == 'ContentQuery':
        layer = 'Queries'
    elif name in content_services:
        layer = 'Services'
    elif name in content_values:
        layer = 'ValueObjects'
    else:
        layer = 'Actions'
    move_php(str(source), f'app/Domain/Content/{layer}/{name}.php', f'App\\Domain\\Content\\{layer}')


# Event, notification, and rally ownership.
rally_actions = {
    'AssignRallyMember',
    'CreateEventRecommendedFormation',
    'CreateRallyGroup',
    'CreateRallyGuidanceRule',
    'RecordRallyParticipation',
    'SaveMemberFormation',
}
notification_actions = {
    'CreateEventReminderRule',
    'MarkEventReminderPublished',
    'QueueDueEventReminders',
    'SyncEventReminderDeliveries',
    'SyncUpcomingEventReminders',
}
event_services = {'EventOutbox', 'RecurrenceCalculator'}
for source in sorted(Path('app/Application/Events').glob('*.php')):
    name = source.stem
    if name in rally_actions:
        domain, layer = 'Rallies', 'Actions'
    elif name in notification_actions:
        domain, layer = 'Notifications', 'Actions'
    elif name == 'AllianceEventQuery':
        domain, layer = 'Events', 'Queries'
    elif name in event_services:
        domain, layer = 'Events', 'Services'
    else:
        domain, layer = 'Events', 'Actions'
    move_php(str(source), f'app/Domain/{domain}/{layer}/{name}.php', f'App\\Domain\\{domain}\\{layer}')


# Split the oversized Identity bucket into its canonical domains.
identity_owners = {
    'AcceptInvitation': ('Memberships', 'Actions'),
    'AllianceAuthorization': ('Authorization', 'Services'),
    'AllianceContext': ('Alliances', 'Services'),
    'AllianceRoleProvisioner': ('Authorization', 'Services'),
    'AssignMembershipRole': ('Authorization', 'Actions'),
    'AuditRecorder': ('Audit', 'Services'),
    'CreateAlliance': ('Alliances', 'Actions'),
    'CreateInvitation': ('Memberships', 'Actions'),
    'FindPendingInvitation': ('Memberships', 'Queries'),
    'InvitationTokenService': ('Memberships', 'Services'),
    'IssuedInvitation': ('Memberships', 'ValueObjects'),
    'LeaveAlliance': ('Memberships', 'Actions'),
    'MembershipAdministrationGuard': ('Memberships', 'Services'),
    'RegisterUser': ('Identity', 'Actions'),
    'RemoveMembershipRole': ('Authorization', 'Actions'),
    'ResendInvitation': ('Memberships', 'Actions'),
    'RevokeInvitation': ('Memberships', 'Actions'),
    'TotpService': ('Identity', 'Services'),
    'TwoFactorManager': ('Identity', 'Services'),
    'UpdateMembershipStatus': ('Memberships', 'Actions'),
}
for source in sorted(Path('app/Application/Identity').glob('*.php')):
    name = source.stem
    if name not in identity_owners:
        raise RuntimeError(f'Unclassified identity application class {name}')
    domain, layer = identity_owners[name]
    move_php(str(source), f'app/Domain/{domain}/{layer}/{name}.php', f'App\\Domain\\{domain}\\{layer}')


# Recruitment ownership.
recruitment_services = {'RecruitmentApplicationTokenService', 'RecruitmentOutbox'}
recruitment_queries = {'PublicRecruitmentQuery', 'RecruitmentDuplicateFinder', 'RecruitmentMetricsQuery'}
recruitment_values = {'ConvertedRecruitmentCandidate', 'IssuedRecruitmentApplicationInvite'}
for source in sorted(Path('app/Application/Recruitment').glob('*.php')):
    name = source.stem
    if name in recruitment_services:
        layer = 'Services'
    elif name in recruitment_queries:
        layer = 'Queries'
    elif name in recruitment_values:
        layer = 'ValueObjects'
    else:
        layer = 'Actions'
    move_php(str(source), f'app/Domain/Recruitment/{layer}/{name}.php', f'App\\Domain\\Recruitment\\{layer}')


move_php(
    'app/Application/Shared/PublishOutboxBatch.php',
    'app/Domain/Platform/Actions/PublishOutboxBatch.php',
    'App\\Domain\\Platform\\Actions',
)
move_php(
    'app/Application/Operations/RuntimeConfigurationValidator.php',
    'app/Domain/Platform/Services/RuntimeConfigurationValidator.php',
    'App\\Domain\\Platform\\Services',
)


# HTTP adapter ownership.
controller_domains = {
    'Controller': 'Platform',
    'DashboardController': 'Alliances',
    'PublicContentController': 'Content',
    'TwoFactorController': 'Identity',
    'PublicAllianceController': 'Alliances',
    'VerifyEmailController': 'Identity',
    'ReadinessController': 'Platform',
    'RegistrationController': 'Identity',
    'ResetPasswordController': 'Identity',
    'MembershipController': 'Memberships',
    'InvitationController': 'Memberships',
    'PublicBrandingMediaController': 'Content',
    'ForgotPasswordController': 'Identity',
    'ConfirmPasswordController': 'Identity',
    'MemberContentController': 'Content',
    'TwoFactorChallengeController': 'Identity',
    'CreateAllianceController': 'Alliances',
    'ProfileController': 'Identity',
    'InvitationAcceptanceController': 'Memberships',
    'ActivateAllianceController': 'Alliances',
    'AuthenticatedSessionController': 'Identity',
    'EmailVerificationPromptController': 'Identity',
    'PublicRecruitmentController': 'Recruitment',
    'EmailVerificationNotificationController': 'Identity',
    'AllianceOverviewController': 'Alliances',
    'EventCalendarController': 'Events',
    'ContentManagementController': 'Content',
    'RecruitmentManagementController': 'Recruitment',
    'RecruitmentCandidateController': 'Recruitment',
    'EventManagementController': 'Events',
}
for source in sorted(Path('app/Http/Controllers').rglob('*.php')):
    name = source.stem
    domain = controller_domains.get(name)
    if domain is None:
        raise RuntimeError(f'Unclassified controller {source}')
    move_php(str(source), f'app/Domain/{domain}/Http/Controllers/{name}.php', f'App\\Domain\\{domain}\\Http\\Controllers')

middleware_domains = {
    'ResolveAllianceContext': 'Alliances',
    'AssignRequestContext': 'Platform',
    'HandleInertiaRequests': 'Platform',
    'RecordRequestMetrics': 'Platform',
    'SecurityHeaders': 'Platform',
}
for source in sorted(Path('app/Http/Middleware').glob('*.php')):
    name = source.stem
    domain = middleware_domains.get(name)
    if domain is None:
        raise RuntimeError(f'Unclassified middleware {source}')
    move_php(str(source), f'app/Domain/{domain}/Http/Middleware/{name}.php', f'App\\Domain\\{domain}\\Http\\Middleware')

for source in sorted(Path('app/Providers').glob('*.php')):
    move_php(str(source), f'app/Domain/Platform/Providers/{source.name}', 'App\\Domain\\Platform\\Providers')


# Update FQCN references in source, tests, routes, configuration, workflows, and docs.
text_suffixes = {'.php', '.md', '.json', '.xml', '.neon', '.yml', '.yaml', '.ts', '.vue'}
text_files = [
    p
    for p in root.rglob('*')
    if p.is_file()
    and p.suffix in text_suffixes
    and '.git' not in p.parts
    and 'vendor' not in p.parts
    and 'node_modules' not in p.parts
]
for path in text_files:
    text = path.read_text(errors='replace')
    changed = text
    for old, new in moves.items():
        changed = changed.replace(old, new)
        changed = changed.replace(old.replace('\\', '\\\\'), new.replace('\\', '\\\\'))
    if changed != text:
        path.write_text(changed)


# Moved files used to share namespaces. Import moved symbols now owned elsewhere.
short_targets: dict[str, str] = {}
for new in moves.values():
    short = new.rsplit('\\', 1)[1]
    if short in short_targets and short_targets[short] != new:
        raise RuntimeError(f'Duplicate moved short class name: {short}')
    short_targets[short] = new

php_files = [p for p in root.rglob('*.php') if '.git' not in p.parts and 'vendor' not in p.parts]
for path in php_files:
    text = path.read_text()
    match = re.search(r'^namespace\s+([^;]+);', text, re.M)
    if not match:
        continue
    namespace = match.group(1)
    declared = path.stem
    additions: list[str] = []
    for short, fqcn in short_targets.items():
        if short == declared:
            continue
        target_ns = fqcn.rsplit('\\', 1)[0]
        if target_ns == namespace:
            continue
        if not re.search(rf'\b{re.escape(short)}\b', text):
            continue
        if re.search(rf'^use\s+[^;]*\\{re.escape(short)}(?:\s+as\s+\w+)?;', text, re.M):
            continue
        additions.append(f'use {fqcn};')
    if additions:
        insert_at = match.end()
        block = '\n\n' + '\n'.join(sorted(set(additions)))
        path.write_text(text[:insert_at] + block + text[insert_at:])


# Canonical domains are explicit even where later-phase runtime code is intentionally absent.
domain_status = {
    'Alliances': 'Implemented in Phase 1; owns alliance lifecycle and active-alliance context.',
    'Audit': 'Implemented foundation; owns privileged activity audit records and recording.',
    'Authorization': 'Implemented in Phase 1; owns roles, permissions, and authorization services.',
    'Content': 'Implemented in Phase 2; owns alliance content, public profile content, and media.',
    'Contributions': 'Reserved for Phase 5. No runtime implementation is permitted before Phase 5.',
    'Events': 'Implemented in Phase 3; owns event scheduling, occurrences, registration, attendance, and recurrence.',
    'Identity': 'Implemented in Phase 1; owns global users, authentication, account lifecycle, and MFA.',
    'Integrations': 'Reserved for later approved integration work. No runtime implementation is present.',
    'Kingdoms': 'Reserved for approved game/kingdom reference capabilities. No runtime implementation is present.',
    'Memberships': 'Implemented in Phase 1; owns alliance memberships and membership invitations.',
    'Notifications': 'Implemented where required by event reminders; owns notification delivery state and reminder dispatch coordination.',
    'Platform': 'Implemented foundation; owns runtime operations, outbox publication, health, request context, and framework composition.',
    'Rallies': 'Implemented in Phase 3; owns formations, rally guidance, rally groups, assignments, and participation.',
    'Recruitment': 'Implemented in Phase 4; owns recruitment intake, pipeline, review, decisions, onboarding, and retention.',
}
for domain, description in domain_status.items():
    readme = Path(f'app/Domain/{domain}/README.md')
    readme.parent.mkdir(parents=True, exist_ok=True)
    if not readme.exists():
        readme.write_text(f'# {domain} domain\n\n{description}\n')


# Remove superseded layer-first trees. There are intentionally no aliases or shims.
for legacy in ['app/Application', 'app/Models', 'app/Http', 'app/Infrastructure', 'app/Providers', 'app/Domain/Shared']:
    path = Path(legacy)
    if path.exists():
        php_left = list(path.rglob('*.php'))
        if php_left:
            raise RuntimeError(f'PHP files remain in legacy tree {legacy}: {php_left}')
        shutil.rmtree(path)


# Architecture regression test.
architecture = Path('tests/Architecture/DomainStructureTest.php')
architecture.parent.mkdir(parents=True, exist_ok=True)
architecture.write_text(r'''<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class DomainStructureTest extends TestCase
{
    private const DOMAINS = [
        'Alliances',
        'Audit',
        'Authorization',
        'Content',
        'Contributions',
        'Events',
        'Identity',
        'Integrations',
        'Kingdoms',
        'Memberships',
        'Notifications',
        'Platform',
        'Rallies',
        'Recruitment',
    ];

    public function test_implementation_plan_domain_directories_are_present(): void
    {
        foreach (self::DOMAINS as $domain) {
            self::assertDirectoryExists($this->root().'/app/Domain/'.$domain);
        }
    }

    public function test_layer_first_legacy_app_directories_are_absent(): void
    {
        foreach (['Application', 'Http', 'Infrastructure', 'Models', 'Providers'] as $legacy) {
            self::assertDirectoryDoesNotExist($this->root().'/app/'.$legacy);
        }

        self::assertDirectoryDoesNotExist($this->root().'/app/Domain/Shared');
    }

    public function test_all_runtime_php_under_app_is_owned_by_a_canonical_domain(): void
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root().'/app'));

        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $path = str_replace('\\', '/', $file->getPathname());
            self::assertMatchesRegularExpression(
                '#/app/Domain/('.implode('|', self::DOMAINS).')/#',
                $path,
                'Runtime PHP must be owned by a canonical domain: '.$path,
            );
        }
    }

    public function test_future_phase_domains_contain_no_runtime_php(): void
    {
        foreach (['Contributions', 'Integrations', 'Kingdoms'] as $domain) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->root().'/app/Domain/'.$domain),
            );

            foreach ($iterator as $file) {
                if ($file instanceof SplFileInfo && $file->isFile()) {
                    self::assertNotSame('php', $file->getExtension(), $domain.' must remain documentation-only until its approved phase.');
                }
            }
        }
    }

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }
}
''')

for directory in ['tests/Integration', 'tests/Performance', 'tests/TenantIsolation']:
    path = Path(directory)
    path.mkdir(parents=True, exist_ok=True)
    readme = path / 'README.md'
    if not readme.exists():
        readme.write_text(f'# {path.name} tests\n\nThis test group is required by the implementation-plan repository structure.\n')


# Final machine guard: app contains only Domain at top level.
app_dirs = sorted(p.name for p in Path('app').iterdir() if p.is_dir())
if app_dirs != ['Domain']:
    raise RuntimeError(f'Unexpected app top-level directories after refactor: {app_dirs}')
