<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Identity\AuditRecorder;
use App\Models\OutboxMessage;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
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

        $emailChanged = ! hash_equals(Str::lower((string) $user->email), $validated['email']);
        $changedFields = [];

        foreach (['name', 'email', 'timezone'] as $field) {
            if ((string) $user->getAttribute($field) !== (string) $validated[$field]) {
                $changedFields[] = $field;
            }
        }

        DB::transaction(function () use ($user, $validated, $emailChanged, $changedFields, $audit): void {
            $user->forceFill([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'timezone' => $validated['timezone'],
                'email_verified_at' => $emailChanged ? null : $user->email_verified_at,
            ])->save();

            $audit->record(
                event: 'profile.updated',
                actor: $user,
                subject: $user,
                metadata: ['changed_fields' => $changedFields],
            );

            OutboxMessage::query()->create([
                'alliance_id' => null,
                'event_type' => 'profile.updated',
                'aggregate_type' => User::class,
                'aggregate_id' => (string) $user->id,
                'idempotency_key' => 'profile.updated:'.$user->id.':'.now()->format('Uu'),
                'payload' => [
                    'user_id' => $user->id,
                    'changed_fields' => $changedFields,
                ],
                'occurred_at' => now(),
                'available_at' => now(),
                'attempts' => 0,
            ]);
        });

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();

            return redirect()->route('verification.notice');
        }

        return redirect()->route('profile.show');
    }

    public function updatePassword(Request $request, AuditRecorder $audit): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $validated = $request->validate([
            'current_password' => ['required', 'current_password:web'],
            'password' => [
                'required',
                'confirmed',
                Password::min(12)->letters()->mixedCase()->numbers(),
            ],
        ]);

        $newPassword = (string) $validated['password'];

        DB::transaction(function () use ($user, $newPassword, $audit): void {
            $user->forceFill([
                'password' => Hash::make($newPassword),
                'remember_token' => Str::random(60),
            ])->save();
            $user->tokens()->delete();

            $audit->record(
                event: 'profile.password.updated',
                actor: $user,
                subject: $user,
            );

            OutboxMessage::query()->create([
                'alliance_id' => null,
                'event_type' => 'profile.password.updated',
                'aggregate_type' => User::class,
                'aggregate_id' => (string) $user->id,
                'idempotency_key' => 'profile.password.updated:'.$user->id.':'.now()->format('Uu'),
                'payload' => ['user_id' => $user->id],
                'occurred_at' => now(),
                'available_at' => now(),
                'attempts' => 0,
            ]);
        });

        Auth::logoutOtherDevices($newPassword);

        return redirect()->route('profile.show')->with('status', 'password-updated');
    }

    public function destroyOtherSessions(Request $request, AuditRecorder $audit): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $validated = $request->validate([
            'password' => ['required', 'current_password:web'],
        ]);

        Auth::logoutOtherDevices((string) $validated['password']);

        $audit->record(
            event: 'auth.other_sessions.revoked',
            actor: $user,
            subject: $user,
        );

        return redirect()->route('profile.show')->with('status', 'other-sessions-revoked');
    }
}
