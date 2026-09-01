<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Security\Queries;

use App\Contexts\Accounts\Identity\Models\User;
use App\Shared\Infrastructure\AuditTrail\Models\AuditEvent;

final class AccountSecurityActivityQuery
{
    /** @return list<array{id:string,event:string,labelKey:string,occurredAt:string}> */
    public function forUser(int $userId, int $limit = 25): array
    {
        return AuditEvent::query()
            ->where(static function ($query) use ($userId): void {
                $query->where('actor_user_id', $userId)
                    ->orWhere(static function ($subjectQuery) use ($userId): void {
                        $subjectQuery->where('subject_type', User::class)
                            ->where('subject_id', (string) $userId);
                    });
            })
            ->where(static function ($query): void {
                $query->where('event', 'like', 'auth.%')
                    ->orWhere('event', 'like', 'profile.password.%')
                    ->orWhere('event', 'like', 'account.%');
            })
            ->latest('created_at')
            ->limit(max(1, min(100, $limit)))
            ->get()
            ->map(fn (AuditEvent $event): array => [
                'id' => (string) $event->id,
                'event' => (string) $event->event,
                'labelKey' => $this->labelKey((string) $event->event),
                'occurredAt' => $event->created_at->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    private function labelKey(string $event): string
    {
        return match ($event) {
            'auth.login' => 'accountExperience.account.activityEvents.signIn',
            'auth.reauthenticated', 'auth.password.confirmed' => 'accountExperience.account.activityEvents.reauthenticated',
            'auth.password.reset' => 'accountExperience.account.activityEvents.passwordReset',
            'profile.password.updated' => 'accountExperience.account.activityEvents.passwordChanged',
            'auth.email.verified' => 'accountExperience.account.activityEvents.emailVerified',
            'auth.email.change_requested' => 'accountExperience.account.activityEvents.emailChangeRequested',
            'auth.email.changed', 'auth.email.provider_updated' => 'accountExperience.account.activityEvents.emailChanged',
            'auth.mfa.enrollment_started' => 'accountExperience.account.activityEvents.mfaEnrollmentStarted',
            'auth.mfa.enabled' => 'accountExperience.account.activityEvents.mfaEnabled',
            'auth.mfa.disabled' => 'accountExperience.account.activityEvents.mfaDisabled',
            'auth.mfa.recovery_codes_regenerated' => 'accountExperience.account.activityEvents.recoveryCodesRegenerated',
            'auth.mfa.recovery_code_used' => 'accountExperience.account.activityEvents.recoveryCodeUsed',
            'auth.session.revoked' => 'accountExperience.account.activityEvents.sessionRevoked',
            'auth.sessions.revoked', 'auth.other_sessions.revoked' => 'accountExperience.account.activityEvents.sessionsRevoked',
            'account.deletion_requested' => 'accountExperience.account.activityEvents.deletionRequested',
            'account.deletion_cancelled' => 'accountExperience.account.activityEvents.deletionCancelled',
            'account.anonymized' => 'accountExperience.account.activityEvents.accountAnonymized',
            default => str_starts_with($event, 'auth.google.')
                ? 'accountExperience.account.activityEvents.googleIdentity'
                : 'accountExperience.account.activityEvents.securityEvent',
        };
    }
}
