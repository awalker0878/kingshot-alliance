<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Authentication\Actions;

use App\Contexts\Accounts\Authentication\Services\RecentAuthentication;
use App\Contexts\Accounts\Identity\Actions\RecordAccountIdentityUse;
use App\Contexts\Accounts\Identity\Models\AccountIdentity;
use App\Contexts\Accounts\Identity\Models\User;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final readonly class ConfirmGoogleAccount
{
    public function __construct(
        private RecordAccountIdentityUse $recordIdentityUse,
        private AuditRecorder $audit,
        private RecentAuthentication $recentAuthentication,
    ) {}

    public function handle(Request $request, ?int $expectedUserId, string $verifiedSubject, string $verifiedEmail): void
    {
        $userId = $request->user()?->getAuthIdentifier();
        abort_unless(is_numeric($userId) && $expectedUserId === (int) $userId, 403);

        $confirmed = DB::transaction(function () use ($request, $userId, $verifiedSubject, $verifiedEmail): bool {
            $user = User::query()->whereKey($userId)->lockForUpdate()->firstOrFail();
            $user->ensureActive();
            $identity = AccountIdentity::query()->where('user_id', $user->id)->where('provider', 'google')->lockForUpdate()->first();
            if ($identity === null) {
                return false;
            }
            if (! hash_equals($identity->provider_subject, $verifiedSubject)) {
                $this->audit->record(event: 'auth.google.identity_failed', actor: $user, subject: $user,
                    metadata: ['reason' => 'reauthentication_subject_mismatch']);

                return false;
            }

            $this->recordIdentityUse->handle((int) $identity->id, $verifiedEmail, true);
            $this->audit->record(event: 'auth.reauthenticated', actor: $user, subject: $user, metadata: ['provider' => 'google']);
            DB::afterCommit(function () use ($request, $userId, $identity, $verifiedSubject): void {
                $active = User::query()->whereKey($userId)->whereNull('anonymized_at')->exists();
                $currentIdentity = AccountIdentity::query()->whereKey($identity->id)->where('user_id', $userId)
                    ->where('provider', 'google')->where('provider_subject', $verifiedSubject)->exists();
                if ($active && $currentIdentity && (string) $request->user()?->getAuthIdentifier() === (string) $userId) {
                    $this->recentAuthentication->mark($request, 'google', (string) $identity->id);
                }
            });

            return true;
        });

        abort_unless($confirmed, 403, 'Google reauthentication did not match this Kingshot Alliance account.');
    }
}
