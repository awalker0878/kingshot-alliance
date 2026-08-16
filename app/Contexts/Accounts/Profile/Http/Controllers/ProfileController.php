<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Profile\Http\Controllers;

use App\Contexts\Accounts\Identity\Models\User;
use App\Shared\Http\Controller;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Models\OutboxMessage;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class ProfileController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return Inertia::render('Profile', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'emailVerified' => $user->hasVerifiedEmail(),
                'timezone' => $user->timezone,
                'twoFactorEnabled' => $user->two_factor_confirmed_at !== null,
                'twoFactorPending' => $user->two_factor_confirmed_at === null
                    && (string) $user->two_factor_secret !== '',
            ],
            'twoFactorSetup' => $request->session()->get('twoFactorSetup'),
            'twoFactorRecoveryCodes' => $request->session()->get('twoFactorRecoveryCodes'),
            'status' => $request->session()->get('status'),
        ]);
    }

    public function update(Request $request, AuditRecorder $audit): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => [
                'required',
                'string',
                'email',
                'max:254',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'timezone' => ['required', 'string', 'timezone'],
        ]);

        try {
            $emailChanged = DB::transaction(function () use ($user, $validated, $audit): bool {
                $currentUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
                $emailChanged = ! hash_equals(Str::lower((string) $currentUser->email), $validated['email']);
                $changedFields = [];
                foreach (['name', 'email', 'timezone'] as $field) {
                    if ((string) $currentUser->getAttribute($field) !== (string) $validated[$field]) {
                        $changedFields[] = $field;
                    }
                }

                if ($changedFields === []) {
                    return false;
                }

                $currentUser->forceFill([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'timezone' => $validated['timezone'],
                    'email_verified_at' => $emailChanged ? null : $currentUser->email_verified_at,
                ])->save();

                $audit->record(
                    event: 'profile.updated',
                    actor: $currentUser,
                    subject: $currentUser,
                    metadata: ['changed_fields' => $changedFields],
                );

                OutboxMessage::query()->create([
                    'alliance_id' => null,
                    'event_type' => 'profile.updated',
                    'aggregate_type' => User::class,
                    'aggregate_id' => (string) $currentUser->id,
                    'idempotency_key' => 'profile.updated:'.$currentUser->id.':'.now()->format('Uu'),
                    'payload' => [
                        'user_id' => $currentUser->id,
                        'changed_fields' => $changedFields,
                    ],
                    'occurred_at' => now(),
                    'available_at' => now(),
                    'attempts' => 0,
                ]);

                return $emailChanged;
            });
        } catch (QueryException $exception) {
            if (User::query()
                ->where('email', $validated['email'])
                ->where('id', '<>', $user->id)
                ->exists()) {
                throw ValidationException::withMessages(['email' => 'The email has already been taken.']);
            }
            throw $exception;
        }

        if ($emailChanged) {
            $currentUser = User::query()->findOrFail($user->id);
            $currentUser->sendEmailVerificationNotification();

            return redirect()->route('verification.notice');
        }

        return redirect()->route('profile.show');
    }

    public function updatePassword(Request $request, AuditRecorder $audit): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => [
                'required',
                'confirmed',
                Password::min(12)->letters()->mixedCase()->numbers(),
            ],
        ]);

        $currentPassword = (string) $validated['current_password'];
        $newPassword = (string) $validated['password'];

        $currentUser = DB::transaction(function () use ($user, $currentPassword, $newPassword, $audit): User {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            if (! Hash::check($currentPassword, (string) $locked->password)) {
                throw ValidationException::withMessages(['current_password' => 'The password is incorrect.']);
            }

            $locked->forceFill([
                'password' => Hash::make($newPassword),
                'remember_token' => Str::random(60),
            ])->save();
            $locked->tokens()->delete();

            $audit->record(
                event: 'profile.password.updated',
                actor: $locked,
                subject: $locked,
            );

            OutboxMessage::query()->create([
                'alliance_id' => null,
                'event_type' => 'profile.password.updated',
                'aggregate_type' => User::class,
                'aggregate_id' => (string) $locked->id,
                'idempotency_key' => 'profile.password.updated:'.$locked->id.':'.now()->format('Uu'),
                'payload' => ['user_id' => $locked->id],
                'occurred_at' => now(),
                'available_at' => now(),
                'attempts' => 0,
            ]);

            return $locked->refresh();
        });

        Auth::setUser($currentUser);
        Auth::logoutOtherDevices($newPassword);

        return redirect()->route('profile.show')->with('status', 'password-updated');
    }

    public function destroyOtherSessions(Request $request, AuditRecorder $audit): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);
        $password = (string) $validated['password'];

        $currentUser = DB::transaction(function () use ($user, $password, $audit): User {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            if (! Hash::check($password, (string) $locked->password)) {
                throw ValidationException::withMessages(['password' => 'The password is incorrect.']);
            }

            $audit->record(
                event: 'auth.other_sessions.revoked',
                actor: $locked,
                subject: $locked,
            );

            return $locked;
        });

        Auth::setUser($currentUser);
        Auth::logoutOtherDevices($password);

        return redirect()->route('profile.show')->with('status', 'other-sessions-revoked');
    }
}
