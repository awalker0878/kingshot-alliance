from __future__ import annotations

from pathlib import Path
import re
import shutil


def move_php(old: str, new: str, old_namespace: str, new_namespace: str) -> None:
    source = Path(old)
    target = Path(new)
    if not source.exists():
        return
    target.parent.mkdir(parents=True, exist_ok=True)
    text = source.read_text().replace(f'namespace {old_namespace};', f'namespace {new_namespace};', 1)
    target.write_text(text)
    source.unlink()


# Public alliance presence is Phase 2 Content ownership, not an Alliances persistence adapter.
move_php(
    'app/Domain/Alliances/Http/Controllers/PublicAllianceController.php',
    'app/Domain/Content/Http/Controllers/PublicAllianceController.php',
    'App\\Domain\\Alliances\\Http\\Controllers',
    'App\\Domain\\Content\\Http\\Controllers',
)
for path in [Path('routes/web.php'), *Path('tests').rglob('*.php')]:
    if not path.exists():
        continue
    text = path.read_text()
    text = text.replace(
        'App\\Domain\\Alliances\\Http\\Controllers\\PublicAllianceController',
        'App\\Domain\\Content\\Http\\Controllers\\PublicAllianceController',
    )
    path.write_text(text)

# Alliance owns its aggregate and core tenancy relations only. Content owns content-side relations/queries.
Path('app/Domain/Alliances/Models/Alliance.php').write_text(r'''<?php

declare(strict_types=1);

namespace App\Domain\Alliances\Models;

use App\Domain\Authorization\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Memberships\Models\AllianceMembership;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Alliance extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'slug',
        'timezone',
        'status',
        'created_by_user_id',
    ];

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return HasMany<AllianceMembership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(AllianceMembership::class);
    }

    /** @return HasMany<Role, $this> */
    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }
}
''')

# Membership invitation public contract returns identifiers, never an internal Eloquent model.
Path('app/Domain/Memberships/ValueObjects/IssuedInvitation.php').write_text(r'''<?php

declare(strict_types=1);

namespace App\Domain\Memberships\ValueObjects;

final readonly class IssuedInvitation
{
    public function __construct(
        public string $invitationId,
        public string $token,
    ) {}
}
''')

for action_path in [
    Path('app/Domain/Memberships/Actions/CreateInvitation.php'),
    Path('app/Domain/Memberships/Actions/ResendInvitation.php'),
]:
    text = action_path.read_text()
    text = text.replace('return new IssuedInvitation($invitation, $token);', 'return new IssuedInvitation((string) $invitation->id, $token);')
    text = text.replace('return new IssuedInvitation($invitation->refresh(), $token);', 'return new IssuedInvitation((string) $invitation->id, $token);')
    action_path.write_text(text)

converted_path = Path('app/Domain/Recruitment/ValueObjects/ConvertedRecruitmentCandidate.php')
converted_path.write_text(r'''<?php

declare(strict_types=1);

namespace App\Domain\Recruitment\ValueObjects;

use App\Domain\Recruitment\Models\RecruitmentCandidate;

final readonly class ConvertedRecruitmentCandidate
{
    public function __construct(
        public RecruitmentCandidate $candidate,
        public string $invitationId,
        public ?string $token,
        public bool $wasCreated,
    ) {}
}
''')

convert_path = Path('app/Domain/Recruitment/Actions/ConvertAcceptedRecruitmentCandidate.php')
text = convert_path.read_text()
text = text.replace('use App\\Domain\\Memberships\\Models\\Invitation;\n', '')
text = re.sub(
    r"\n            if \(\$locked->membership_invitation_id !== null\) \{\n                \$existing = Invitation::query\(\)\n                    ->where\('alliance_id', \$alliance->id\)\n                    ->whereKey\(\$locked->membership_invitation_id\)\n                    ->firstOrFail\(\);\n\n                return new ConvertedRecruitmentCandidate\(\$locked, \$existing, null, false\);\n            \}",
    "\n            if ($locked->membership_invitation_id !== null) {\n                return new ConvertedRecruitmentCandidate(\n                    $locked,\n                    (string) $locked->membership_invitation_id,\n                    null,\n                    false,\n                );\n            }",
    text,
)
text = text.replace("'membership_invitation_id' => $issued->invitation->id", "'membership_invitation_id' => $issued->invitationId")
text = text.replace("'membership_invitation_id' => $issued->invitation->id,", "'membership_invitation_id' => $issued->invitationId,")
text = text.replace('$issued->invitation->id', '$issued->invitationId')
text = text.replace('$issued->invitation,', '$issued->invitationId,')
convert_path.write_text(text)

# Update invitation tests to query Memberships persistence explicitly only inside Memberships tests.
invitation_test = Path('tests/Feature/Identity/InvitationLifecycleTest.php')
if invitation_test.exists():
    text = invitation_test.read_text()
    if 'use App\\Domain\\Memberships\\Models\\Invitation;' not in text:
        text = text.replace(
            'use App\\Domain\\Memberships\\Models\\AllianceMembership;\n',
            'use App\\Domain\\Memberships\\Models\\AllianceMembership;\nuse App\\Domain\\Memberships\\Models\\Invitation;\n',
        )
    text = text.replace('$issued->invitation->id', '$issued->invitationId')
    text = text.replace('$resent->invitation->id', '$resent->invitationId')
    text = text.replace('$issued->invitation->email', 'Invitation::query()->findOrFail($issued->invitationId)->email')
    text = text.replace('$issued->invitation->token_hash', 'Invitation::query()->findOrFail($issued->invitationId)->token_hash')
    text = text.replace('$issued->invitation->refresh()', 'Invitation::query()->findOrFail($issued->invitationId)->refresh()')
    text = text.replace('$resent->invitation->refresh()', 'Invitation::query()->findOrFail($resent->invitationId)->refresh()')
    invitation_test.write_text(text)

coordination_test = Path('tests/Feature/Recruitment/RecruitmentCoordinationTest.php')
if coordination_test.exists():
    text = coordination_test.read_text()
    text = text.replace('$converted->invitation->id', '$converted->invitationId')
    text = text.replace('$repeat->invitation->id', '$repeat->invitationId')
    coordination_test.write_text(text)

# Centralize identical Content/Event/Recruitment outbox writers under Platform.
outbox = Path('app/Domain/Platform/Services/OutboxRecorder.php')
outbox.parent.mkdir(parents=True, exist_ok=True)
outbox.write_text(r'''<?php

declare(strict_types=1);

namespace App\Domain\Platform\Services;

use App\Domain\Platform\Models\OutboxMessage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class OutboxRecorder
{
    /** @param array<string, mixed> $payload */
    public function record(
        string $eventType,
        ?string $allianceId,
        Model $aggregate,
        array $payload = [],
        ?string $idempotencyKey = null,
    ): OutboxMessage {
        return OutboxMessage::query()->create([
            'alliance_id' => $allianceId,
            'event_type' => $eventType,
            'aggregate_type' => $aggregate->getMorphClass(),
            'aggregate_id' => (string) $aggregate->getKey(),
            'idempotency_key' => $idempotencyKey ?? $eventType.':'.$aggregate->getKey().':'.Str::ulid(),
            'payload' => ($allianceId === null ? [] : ['alliance_id' => $allianceId]) + $payload,
            'occurred_at' => now(),
            'available_at' => now(),
            'attempts' => 0,
        ]);
    }
}
''')

old_outboxes = {
    'App\\Domain\\Content\\Services\\ContentOutbox': 'ContentOutbox',
    'App\\Domain\\Events\\Services\\EventOutbox': 'EventOutbox',
    'App\\Domain\\Recruitment\\Services\\RecruitmentOutbox': 'RecruitmentOutbox',
}
for path in Path('app/Domain').rglob('*.php'):
    text = path.read_text()
    changed = text
    used_outbox = False
    for fqcn, short in old_outboxes.items():
        if fqcn in changed or re.search(rf'\b{short}\b', changed):
            used_outbox = True
            changed = changed.replace(fqcn, 'App\\Domain\\Platform\\Services\\OutboxRecorder')
            changed = re.sub(rf'\b{short}\b', 'OutboxRecorder', changed)
    if used_outbox:
        # Feature-local writers all passed the Alliance object as the second argument.
        changed = re.sub(
            r"(\$this->outbox->record\(\s*[^,\n]+,\s*)\$alliance(\s*,)",
            r"\1(string) $alliance->id\2",
            changed,
        )
    if changed != text:
        path.write_text(changed)

for legacy in [
    Path('app/Domain/Content/Services/ContentOutbox.php'),
    Path('app/Domain/Events/Services/EventOutbox.php'),
    Path('app/Domain/Recruitment/Services/RecruitmentOutbox.php'),
]:
    legacy.unlink(missing_ok=True)

# Reclassify tests by the domain they exercise while retaining the canonical top-level groups.
def move_test(old: str, new: str, old_ns: str, new_ns: str) -> None:
    source = Path(old)
    target = Path(new)
    if not source.exists():
        return
    target.parent.mkdir(parents=True, exist_ok=True)
    text = source.read_text().replace(f'namespace {old_ns};', f'namespace {new_ns};', 1)
    target.write_text(text)
    source.unlink()

for name in ['CreateAllianceTest.php', 'ActiveAllianceHttpTest.php']:
    move_test(
        f'tests/Feature/Identity/{name}',
        f'tests/Feature/Alliances/{name}',
        'Tests\\Feature\\Identity',
        'Tests\\Feature\\Alliances',
    )
for name in ['InvitationLifecycleTest.php', 'InvitationReplacementTest.php', 'MembershipAdministrationTest.php']:
    move_test(
        f'tests/Feature/Identity/{name}',
        f'tests/Feature/Memberships/{name}',
        'Tests\\Feature\\Identity',
        'Tests\\Feature\\Memberships',
    )
move_test(
    'tests/Feature/Events/RallyCoordinationTest.php',
    'tests/Feature/Rallies/RallyCoordinationTest.php',
    'Tests\\Feature\\Events',
    'Tests\\Feature\\Rallies',
)
move_test(
    'tests/Feature/Events/EventReminderDeliveryTest.php',
    'tests/Feature/Notifications/EventReminderDeliveryTest.php',
    'Tests\\Feature\\Events',
    'Tests\\Feature\\Notifications',
)
move_test(
    'tests/TenantIsolation/Identity/AllianceIsolationTest.php',
    'tests/TenantIsolation/Alliances/AllianceIsolationTest.php',
    'Tests\\TenantIsolation\\Identity',
    'Tests\\TenantIsolation\\Alliances',
)

# Guard against reintroducing the specific boundary leaks removed here.
architecture = Path('tests/Architecture/DomainBoundaryTest.php')
architecture.write_text(r'''<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class DomainBoundaryTest extends TestCase
{
    public function test_alliance_model_does_not_own_content_relationships(): void
    {
        $source = file_get_contents($this->root().'/app/Domain/Alliances/Models/Alliance.php');
        self::assertIsString($source);
        self::assertStringNotContainsString('App\\Domain\\Content\\Models', $source);
    }

    public function test_recruitment_does_not_import_membership_invitation_persistence(): void
    {
        foreach ($this->phpFiles($this->root().'/app/Domain/Recruitment') as $file) {
            $source = file_get_contents($file);
            self::assertIsString($source);
            self::assertStringNotContainsString('App\\Domain\\Memberships\\Models\\Invitation', $source, $file);
        }
    }

    public function test_feature_domains_use_platform_outbox_recorder_instead_of_duplicate_writers(): void
    {
        foreach (['ContentOutbox.php', 'EventOutbox.php', 'RecruitmentOutbox.php'] as $legacy) {
            $matches = glob($this->root().'/app/Domain/*/Services/'.$legacy) ?: [];
            self::assertSame([], $matches, $legacy.' must not be duplicated in a feature domain.');
        }

        self::assertFileExists($this->root().'/app/Domain/Platform/Services/OutboxRecorder.php');
    }

    /** @return list<string> */
    private function phpFiles(string $directory): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));

        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }
}
''')

# Sanity checks for accidental compatibility residues.
for legacy in [
    Path('app/Domain/Alliances/Http/Controllers/PublicAllianceController.php'),
    Path('app/Domain/Content/Services/ContentOutbox.php'),
    Path('app/Domain/Events/Services/EventOutbox.php'),
    Path('app/Domain/Recruitment/Services/RecruitmentOutbox.php'),
]:
    if legacy.exists():
        raise RuntimeError(f'Legacy boundary artifact still exists: {legacy}')

for path in Path('app/Domain/Recruitment').rglob('*.php'):
    source = path.read_text()
    if 'App\\Domain\\Memberships\\Models\\Invitation' in source:
        raise RuntimeError(f'Recruitment still imports Invitation persistence: {path}')
