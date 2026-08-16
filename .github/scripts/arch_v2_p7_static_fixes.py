import re
from pathlib import Path


def replace(path: str, old: str, new: str, *, count: int | None = None) -> None:
    target = Path(path)
    if not target.is_file():
        raise RuntimeError(f'Missing post-cut file: {path}')
    source = target.read_text(encoding='utf-8')
    hits = source.count(old)
    expected = count if count is not None else 1
    if hits != expected:
        raise RuntimeError(f'{path}: expected {expected} occurrence(s), found {hits}: {old!r}')
    target.write_text(source.replace(old, new, expected), encoding='utf-8')


# Reminder delivery reads Operations-owned state through the actual V2 capability
# owners. Do not resurrect the old EventCore mega-namespace as aliases.
replace(
    'app/Contexts/Communications/Reminders/Actions/QueueDueEventReminders.php',
    'use App\\Contexts\\Operations\\EventCore\\Models\\EventPoll;',
    'use App\\Contexts\\Operations\\Polls\\Models\\EventPoll;',
)

resolver = 'app/Contexts/Communications/Reminders/Services/EventReminderAudienceResolver.php'
replace(
    resolver,
    'use App\\Contexts\\Operations\\EventCore\\Models\\EventRegistration;',
    'use App\\Contexts\\Operations\\Participation\\Models\\EventRegistration;',
)
replace(
    resolver,
    'use App\\Contexts\\Operations\\EventCore\\Models\\EventResponse;',
    'use App\\Contexts\\Operations\\Participation\\Models\\EventResponse;',
)
replace(
    resolver,
    'use App\\Contexts\\Operations\\EventCore\\Models\\EventRosterMember;',
    'use App\\Contexts\\Operations\\Rosters\\Models\\EventRosterMember;',
)

# PHPStan correctly treats array_values(array<int, string>) as list<string>, while
# Collection::values()->all() remains only array<int, string> in Larastan's model.
replace(
    resolver,
    """            EventScope::Kingdom => Player::query()
                ->where('current_kingdom_id', $event->kingdom_id)
                ->whereNotNull('user_id')
                ->pluck('id')
                ->map(static fn ($id): string => (string) $id)
                ->all(),""",
    """            EventScope::Kingdom => array_values(Player::query()
                ->where('current_kingdom_id', $event->kingdom_id)
                ->whereNotNull('user_id')
                ->pluck('id')
                ->map(static fn ($id): string => (string) $id)
                ->all()),""",
)
replace(
    resolver,
    """        return AllianceRosterEntry::query()
            ->where('alliance_id', $alliance->id)
            ->where('state', RosterState::Active->value)
            ->whereHas('player', static fn ($query) => $query
                ->where('current_kingdom_id', $alliance->kingdom_id)
                ->whereNotNull('user_id'))
            ->pluck('player_id')
            ->map(static fn ($id): string => (string) $id)
            ->all();""",
    """        return array_values(AllianceRosterEntry::query()
            ->where('alliance_id', $alliance->id)
            ->where('state', RosterState::Active->value)
            ->whereHas('player', static fn ($query) => $query
                ->where('current_kingdom_id', $alliance->kingdom_id)
                ->whereNotNull('user_id'))
            ->pluck('player_id')
            ->map(static fn ($id): string => (string) $id)
            ->all());""",
)

# Intelligence owns the interpretation of Alliance membership for Intelligence
# capabilities. Read admission is any active Player membership in the active
# Alliance/Kingdom; management remains the stricter Intelligence-owned rank rule.
replace(
    'app/Contexts/Intelligence/Access/Enums/IntelligencePermission.php',
    """enum IntelligencePermission: string implements Permission
{
    case ContributionManage = 'contributions.manage';""",
    """enum IntelligencePermission: string implements Permission
{
    case View = 'intelligence.view';
    case ContributionManage = 'contributions.manage';""",
)
replace(
    'app/Contexts/Intelligence/Access/Enums/IntelligencePermission.php',
    """        return match ($this) {
            self::ContributionManage => 'Manage alliance contribution records, reporting, exports, and report schedules.',""",
    """        return match ($this) {
            self::View => 'View Intelligence capabilities for the active Player Alliance context.',
            self::ContributionManage => 'Manage alliance contribution records, reporting, exports, and report schedules.',""",
)
replace(
    'app/Contexts/Intelligence/Access/Services/AllianceIntelligenceAuthorization.php',
    """        return match ($permission) {
            IntelligencePermission::KingdomManage => in_array($membership->rank, [AllianceRank::R4, AllianceRank::R5], true),""",
    """        return match ($permission) {
            IntelligencePermission::View => true,
            IntelligencePermission::KingdomManage => in_array($membership->rank, [AllianceRank::R4, AllianceRank::R5], true),""",
)

# P6 left eight Intelligence read controllers coupled directly to Alliance's
# authorization service for AlliancePermission::View. Each affected method
# already receives Intelligence's own policy for its management decisions. Fold
# the read gate into that local policy and remove the upward permission vocabulary.
intelligence_root = Path('app/Contexts/Intelligence')
direct_permission_import = 'use App\\Contexts\\Alliance\\Access\\Enums\\AlliancePermission;'
direct_authorization_import = 'use App\\Contexts\\Alliance\\Access\\Services\\AllianceAuthorization;'
local_permission_import = 'use App\\Contexts\\Intelligence\\Access\\Enums\\IntelligencePermission;'
local_authorization_import = 'use App\\Contexts\\Intelligence\\Access\\Services\\AllianceIntelligenceAuthorization;'
converted_intelligence_controllers: list[str] = []

for target in sorted(intelligence_root.rglob('*.php')):
    source = target.read_text(encoding='utf-8')
    if direct_authorization_import not in source:
        continue

    if direct_permission_import not in source:
        raise RuntimeError(f'{target}: direct AllianceAuthorization import has no AlliancePermission import.')
    if local_permission_import not in source or local_authorization_import not in source:
        raise RuntimeError(f'{target}: Intelligence controller lacks its context-owned authorization imports.')
    if source.count('AlliancePermission::View') != 1:
        raise RuntimeError(f'{target}: expected exactly one direct Alliance View gate.')

    view_position = source.index('AlliancePermission::View')
    method_start = source.rfind('public function ', 0, view_position)
    signature_end = source.find('):', method_start)
    if method_start < 0 or signature_end < 0:
        raise RuntimeError(f'{target}: could not resolve the method signature for the Alliance View gate.')

    signature = source[method_start:signature_end]
    direct_variables = re.findall(r'AllianceAuthorization \$(\w+)', signature)
    local_variables = re.findall(r'AllianceIntelligenceAuthorization \$(\w+)', signature)
    if len(direct_variables) != 1 or len(local_variables) != 1:
        raise RuntimeError(
            f'{target}: expected one direct and one Intelligence authorization parameter in the read method; '
            f'found direct={direct_variables}, local={local_variables}.'
        )

    direct_variable = direct_variables[0]
    local_variable = local_variables[0]
    direct_parameter = re.compile(rf'^[ \t]*AllianceAuthorization \${re.escape(direct_variable)},\n', re.MULTILINE)
    source, parameter_count = direct_parameter.subn('', source, count=1)
    if parameter_count != 1:
        raise RuntimeError(f'{target}: could not remove the direct Alliance authorization parameter.')

    direct_call = re.compile(
        rf'\${re.escape(direct_variable)}->allows\(([^;\n]+?), AlliancePermission::View\)'
    )
    source, call_count = direct_call.subn(
        lambda match: f'${local_variable}->allows({match.group(1)}, IntelligencePermission::View)',
        source,
        count=1,
    )
    if call_count != 1:
        raise RuntimeError(f'{target}: could not rewrite the direct Alliance View authorization call.')

    source = source.replace(direct_permission_import + '\n', '', 1)
    source = source.replace(direct_authorization_import + '\n', '', 1)
    if direct_permission_import in source or direct_authorization_import in source or 'AlliancePermission::View' in source:
        raise RuntimeError(f'{target}: direct Alliance read authorization survived the Intelligence conversion.')

    target.write_text(source, encoding='utf-8')
    converted_intelligence_controllers.append(str(target))

if len(converted_intelligence_controllers) != 8:
    raise RuntimeError(
        'Expected to convert 8 Intelligence controllers away from direct Alliance authorization; '
        f'converted {len(converted_intelligence_controllers)}: {converted_intelligence_controllers}'
    )

# This controller is self-contained; the legacy file extended a non-existent
# sibling Controller and never used inherited behavior.
replace(
    'app/Contexts/Platform/Http/Controllers/PlatformAdministrationController.php',
    'final class PlatformAdministrationController extends Controller',
    'final class PlatformAdministrationController',
)

# Platform mutations use the already-established V2 Platform access authority.
for service in (
    'app/Contexts/Platform/Services/AllianceDataExportService.php',
    'app/Contexts/Platform/Services/LegalHoldService.php',
):
    replace(
        service,
        'use App\\Contexts\\Accounts\\Models\\User;',
        'use App\\Contexts\\Accounts\\Models\\User;\nuse App\\Contexts\\Platform\\Access\\Services\\PlatformMutationAuthority;',
    )

# Materialize the UI payload through array_values so its list contract is also
# explicit to Larastan.
middleware = Path('app/Contexts/Platform/Http/Middleware/HandleInertiaRequests.php')
source = middleware.read_text(encoding='utf-8')
old = "'players' => $players->map(static fn (Player $player): array => ["
if source.count(old) != 1:
    raise RuntimeError('HandleInertiaRequests: player map shape changed unexpectedly.')
source = source.replace(old, "'players' => array_values($players->map(static fn (Player $player): array => [", 1)
old_tail = "])->values()->all(),"
if source.count(old_tail) != 1:
    raise RuntimeError('HandleInertiaRequests: player list tail changed unexpectedly.')
source = source.replace(old_tail, "])->all()),", 1)
middleware.write_text(source, encoding='utf-8')

# Internal outbox publication must never implicitly become an external webhook
# contract. Use one public-event catalog for both subscription validation and
# fan-out eligibility; a wildcard means "all catalogued public events", not all
# internal messages. The two events below are the public contracts evidenced by
# the current UI/default and integration behavior suite.
catalog = Path('app/Contexts/Platform/Integrations/Contracts/WebhookEventCatalog.php')
catalog.parent.mkdir(parents=True, exist_ok=True)
catalog.write_text(
    """<?php

declare(strict_types=1);

namespace App\\Contexts\\Platform\\Integrations\\Contracts;

final class WebhookEventCatalog
{
    /** @var list<string> */
    private const PUBLIC_EVENTS = [
        'alliance.created',
        'member.joined',
    ];

    public static function isPublic(string $eventType): bool
    {
        return in_array($eventType, self::PUBLIC_EVENTS, true);
    }

    public static function isValidSelector(string $eventType): bool
    {
        return $eventType === '*' || self::isPublic($eventType);
    }

    /** @return list<string> */
    public static function publicEvents(): array
    {
        return self::PUBLIC_EVENTS;
    }
}
""",
    encoding='utf-8',
)

subscription = 'app/Contexts/Platform/Integrations/Actions/CreateWebhookSubscription.php'
replace(
    subscription,
    'use App\\Contexts\\Platform\\Integrations\\Models\\WebhookSubscription;',
    'use App\\Contexts\\Platform\\Integrations\\Contracts\\WebhookEventCatalog;\nuse App\\Contexts\\Platform\\Integrations\\Models\\WebhookSubscription;',
)
replace(
    subscription,
    """        foreach ($events as $event) {
            if ($event !== '*' && preg_match('/^[a-z0-9._-]{3,120}$/', $event) !== 1) {
                throw ValidationException::withMessages(['events' => 'Webhook event names contain an unsupported value.']);
            }
        }
""",
    """        foreach ($events as $event) {
            if (! WebhookEventCatalog::isValidSelector($event)) {
                throw ValidationException::withMessages(['events' => 'Choose only supported public webhook event types or wildcard (*).']);
            }
        }
""",
)

queue = 'app/Contexts/Platform/Integrations/Actions/QueueWebhookDeliveries.php'
replace(
    queue,
    'use App\\Contexts\\Platform\\Integrations\\Enums\\WebhookDeliveryStatus;',
    'use App\\Contexts\\Platform\\Integrations\\Contracts\\WebhookEventCatalog;\nuse App\\Contexts\\Platform\\Integrations\\Enums\\WebhookDeliveryStatus;',
)
replace(
    queue,
    """    private function isExternallyContracted(string $eventType): bool
    {
        return ! str_starts_with($eventType, 'kingdoms.');
    }
""",
    """    private function isExternallyContracted(string $eventType): bool
    {
        return WebhookEventCatalog::isPublic($eventType);
    }
""",
)

# Make the living code-owner README match the hard external contract.
integration_readme = Path('app/Contexts/Platform/Integrations/README.md')
if integration_readme.is_file():
    text = integration_readme.read_text(encoding='utf-8')
    text = text.replace(
        'Internal outbox publication does not automatically create a public webhook contract; current Kingdoms events remain explicitly excluded.',
        'Internal outbox publication does not automatically create a public webhook contract. Webhook fan-out is allowlisted by `WebhookEventCatalog`; wildcard subscriptions mean all catalogued public events, never all internal outbox messages.',
    )
    integration_readme.write_text(text, encoding='utf-8')

print(
    'Applied P7 V2 dependency, Intelligence authorization, explicit list-contract, '
    'and webhook allowlist corrections.'
)
