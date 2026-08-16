from __future__ import annotations

from pathlib import Path


def require_file(path: str) -> Path:
    target = Path(path)
    if not target.is_file():
        raise RuntimeError(f'Missing expected file: {path}')
    return target


def replace_exact(path: str, old: str, new: str, count: int = 1) -> None:
    target = require_file(path)
    source = target.read_text(encoding='utf-8')
    hits = source.count(old)
    if hits != count:
        raise RuntimeError(f'{path}: expected {count} occurrence(s), found {hits}: {old!r}')
    target.write_text(source.replace(old, new, count), encoding='utf-8')


def add_use_after(path: str, anchor: str, import_line: str) -> None:
    target = require_file(path)
    source = target.read_text(encoding='utf-8')
    if import_line in source:
        return
    if source.count(anchor) != 1:
        raise RuntimeError(f'{path}: expected one import anchor {anchor!r}')
    target.write_text(source.replace(anchor, anchor + import_line, 1), encoding='utf-8')


# Downstream workflow authorization needs the Alliance aggregate locked exclusively
# for singleton lifecycle transitions, but Alliance remains the owner of that lock.
authority_path = 'app/Contexts/Alliance/Access/Services/AllianceMutationAuthority.php'
replace_exact(
    authority_path,
    '''    public function acquireActiveScope(Player $actor, Alliance $alliance): AllianceMutationContext
    {
        return $this->acquire($actor, $alliance, false);
    }

    private function acquire(
''',
    '''    public function acquireActiveScope(Player $actor, Alliance $alliance): AllianceMutationContext
    {
        return $this->acquire($actor, $alliance, false);
    }

    /**
     * Lock the Alliance exclusively without interpreting a downstream context or
     * workflow permission vocabulary. The caller remains responsible for policy.
     */
    public function acquireExclusiveScope(Player $actor, Alliance $alliance): AllianceMutationContext
    {
        return $this->acquire($actor, $alliance, true);
    }

    private function acquire(
''',
)

access_enums = Path('app/Workflows/KingdomTransfer/Access/Enums')
access_services = Path('app/Workflows/KingdomTransfer/Access/Services')
access_enums.mkdir(parents=True, exist_ok=True)
access_services.mkdir(parents=True, exist_ok=True)

(access_enums / 'TransferPermission.php').write_text(r'''<?php

declare(strict_types=1);

namespace App\Workflows\KingdomTransfer\Access\Enums;

enum TransferPermission: string
{
    case Manage = 'kingdom_transfer.manage';
}
''', encoding='utf-8')

(access_services / 'TransferAuthorization.php').write_text(r'''<?php

declare(strict_types=1);

namespace App\Workflows\KingdomTransfer\Access\Services;

use App\Contexts\Alliance\Core\Enums\AllianceStatus;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\GameWorld\Models\Player;
use App\Workflows\KingdomTransfer\Access\Enums\TransferPermission;

final class TransferAuthorization
{
    public function allows(Player $actor, Alliance $alliance, TransferPermission $permission): bool
    {
        if ($alliance->status !== AllianceStatus::Active
            || (string) $actor->current_kingdom_id !== (string) $alliance->kingdom_id) {
            return false;
        }

        $membership = AllianceMembership::query()
            ->where('alliance_id', $alliance->id)
            ->where('player_id', $actor->id)
            ->where('status', MembershipStatus::Active->value)
            ->first();

        return $membership instanceof AllianceMembership
            && $this->allowsMembership($membership, $alliance, $permission);
    }

    public function allowsMembership(
        AllianceMembership $membership,
        Alliance $alliance,
        TransferPermission $permission,
    ): bool {
        if ($membership->status !== MembershipStatus::Active
            || (string) $membership->alliance_id !== (string) $alliance->id) {
            return false;
        }

        return match ($permission) {
            // Preserve the established Transfer management contract: R4/R5 may
            // coordinate a transfer cycle. This is workflow policy, not Intelligence.
            TransferPermission::Manage => in_array($membership->rank, [AllianceRank::R4, AllianceRank::R5], true),
        };
    }
}
''', encoding='utf-8')

(access_services / 'TransferMutationAuthority.php').write_text(r'''<?php

declare(strict_types=1);

namespace App\Workflows\KingdomTransfer\Access\Services;

use App\Contexts\Alliance\Access\Services\AllianceMutationAuthority;
use App\Contexts\Alliance\Access\ValueObjects\AllianceMutationContext;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\Player;
use App\Workflows\KingdomTransfer\Access\Enums\TransferPermission;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class TransferMutationAuthority
{
    public function __construct(
        private AllianceMutationAuthority $scopeAuthority,
        private TransferAuthorization $authorization,
    ) {}

    public function require(
        Player $actor,
        Alliance $alliance,
        TransferPermission $permission,
    ): AllianceMutationContext {
        $context = $this->scopeAuthority->acquireActiveScope($actor, $alliance);

        if (! $this->authorization->allowsMembership($context->membership, $context->alliance, $permission)) {
            throw new AuthorizationException;
        }

        return $context;
    }

    public function requireExclusive(
        Player $actor,
        Alliance $alliance,
        TransferPermission $permission,
    ): AllianceMutationContext {
        $context = $this->scopeAuthority->acquireExclusiveScope($actor, $alliance);

        if (! $this->authorization->allowsMembership($context->membership, $context->alliance, $permission)) {
            throw new AuthorizationException;
        }

        return $context;
    }
}
''', encoding='utf-8')

workflow = Path('app/Workflows/KingdomTransfer')
if not workflow.is_dir():
    raise RuntimeError('P8A static fixes require the generated KingdomTransfer workflow tree.')

# Transfer owns its permission vocabulary. Intelligence is neither the workflow owner
# nor an authorization adapter for Transfer.
for path in sorted(workflow.rglob('*.php')):
    source = path.read_text(encoding='utf-8')
    updated = source.replace(
        'use App\\Contexts\\Intelligence\\Access\\Enums\\IntelligencePermission;\n',
        'use App\\Workflows\\KingdomTransfer\\Access\\Enums\\TransferPermission;\n',
    )
    updated = updated.replace(
        'use App\\Contexts\\Intelligence\\Access\\Services\\AllianceIntelligenceMutationAuthority;\n',
        'use App\\Workflows\\KingdomTransfer\\Access\\Services\\TransferMutationAuthority;\n',
    )
    updated = updated.replace('AllianceIntelligenceMutationAuthority', 'TransferMutationAuthority')
    updated = updated.replace('IntelligencePermission::KingdomManage', 'TransferPermission::Manage')
    if updated != source:
        path.write_text(updated, encoding='utf-8')

# ResolveKingdom was already extracted to GameWorld in P3. Transfer consumes that
# owner API; it does not recreate a workflow-local resolver.
for filename in ('SaveTransferGroup.php', 'SaveTransferParticipant.php'):
    add_use_after(
        f'app/Workflows/KingdomTransfer/Actions/{filename}',
        'use App\\Contexts\\GameWorld\\Models\\Player;\n',
        'use App\\Contexts\\GameWorld\\Actions\\ResolveKingdom;\n',
    )

# Restore true relationship owners that were previously implicit only because all
# legacy Kingdoms models shared one namespace.
add_use_after(
    'app/Workflows/KingdomTransfer/Models/TransferGroup.php',
    'use App\\Contexts\\Alliance\\Core\\Models\\Alliance;\n',
    'use App\\Contexts\\GameWorld\\Models\\Kingdom;\nuse App\\Contexts\\GameWorld\\Models\\Player;\n',
)
add_use_after(
    'app/Workflows/KingdomTransfer/Models/TransferParticipant.php',
    'use App\\Contexts\\Alliance\\Core\\Models\\Alliance;\n',
    'use App\\Contexts\\Alliance\\Membership\\Models\\AllianceRosterEntry;\nuse App\\Contexts\\GameWorld\\Models\\Kingdom;\nuse App\\Contexts\\GameWorld\\Models\\Player;\n',
)
add_use_after(
    'app/Workflows/KingdomTransfer/Models/TransferCompletion.php',
    'use App\\Contexts\\Alliance\\Core\\Models\\Alliance;\n',
    'use App\\Contexts\\Alliance\\Membership\\Models\\AllianceRosterEntry;\n',
)
replace_exact(
    'app/Workflows/KingdomTransfer/Models/TransferCompletion.php',
    '@property-read User|null $completedBy',
    '@property-read Player|null $completedBy',
)
replace_exact(
    'app/Workflows/KingdomTransfer/Models/TransferReadinessTransition.php',
    '@property-read User|null $actor',
    '@property-read Player|null $actor',
)

# Larastan requires explicit iterable value types; keep the existing precise shape
# while making the docblocks parse as separate annotations.
participant_action = 'app/Workflows/KingdomTransfer/Actions/SaveTransferParticipant.php'
replace_exact(
    participant_action,
    '/** @param array<string, mixed> $attributes @return array<string, string|null> */',
    '''/**
     * @param  array<string, mixed>  $attributes
     * @return array<string, string|null>
     */''',
    count=2,
)

# Controller reads may continue using Alliance View, but Transfer management checks
# use the workflow's own authorization policy. Preserve the read/manage distinction.
controllers = Path('app/Workflows/KingdomTransfer/Http/Controllers')
for path in sorted(controllers.glob('Transfer*Controller.php')):
    source = path.read_text(encoding='utf-8')
    if 'TransferPermission::Manage' not in source:
        continue

    transfer_import = 'use App\\Workflows\\KingdomTransfer\\Access\\Services\\TransferAuthorization;\n'
    if 'AlliancePermission::' in source:
        if transfer_import not in source:
            anchor = 'use App\\Workflows\\KingdomTransfer\\Access\\Enums\\TransferPermission;\n'
            if source.count(anchor) != 1:
                raise RuntimeError(f'{path}: expected TransferPermission import anchor.')
            source = source.replace(anchor, anchor + transfer_import, 1)

        injection = '        AllianceAuthorization $authorization,\n'
        if injection not in source:
            raise RuntimeError(f'{path}: expected AllianceAuthorization injection for mixed read/manage controller.')
        source = source.replace(
            injection,
            injection + '        TransferAuthorization $transferAuthorization,\n',
        )
        source = source.replace(
            '$authorization->allows($context->player(), $alliance, TransferPermission::Manage)',
            '$transferAuthorization->allows($context->player(), $alliance, TransferPermission::Manage)',
        )
    else:
        old_import = 'use App\\Contexts\\Alliance\\Access\\Services\\AllianceAuthorization;\n'
        if old_import not in source:
            raise RuntimeError(f'{path}: expected AllianceAuthorization import for Transfer-only management controller.')
        source = source.replace(old_import, transfer_import, 1)
        source = source.replace('AllianceAuthorization $authorization', 'TransferAuthorization $authorization')

    path.write_text(source, encoding='utf-8')

# The generated workflow must be independent of Intelligence's access vocabulary.
stale = []
for path in sorted(workflow.rglob('*.php')):
    source = path.read_text(encoding='utf-8')
    if 'App\\Contexts\\Intelligence\\Access\\' in source or 'IntelligencePermission::KingdomManage' in source:
        stale.append(str(path))
if stale:
    raise RuntimeError('P8A left Transfer coupled to Intelligence authorization: ' + ', '.join(stale))

print('Applied P8A static ownership, relationship, typing, and Transfer authorization corrections.')
