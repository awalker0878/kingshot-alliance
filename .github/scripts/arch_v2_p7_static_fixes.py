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

print('Applied P7 V2 dependency and explicit list-contract corrections.')
