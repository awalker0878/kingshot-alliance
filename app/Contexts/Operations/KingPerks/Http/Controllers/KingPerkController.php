<?php

declare(strict_types=1);

namespace App\Contexts\Operations\KingPerks\Http\Controllers;

use App\Contexts\Accounts\Identity\Contracts\AuthenticatedAccount;
use App\Contexts\GameWorld\Players\Services\PlayerContext;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\KingPerks\Actions\ReplaceNoShowAppointment;
use App\Contexts\Operations\KingPerks\Enums\KingAppointmentType;
use App\Contexts\Operations\KingPerks\Enums\KingPerkAppointmentStatus;
use App\Contexts\Operations\KingPerks\Enums\KingPerkPushCategory;
use App\Contexts\Operations\KingPerks\Enums\KingSkill;
use App\Contexts\Operations\KingPerks\Queries\KingPerkScheduleQuery;
use App\Contexts\Operations\KingPerks\Services\KingPerkAutoScheduler;
use App\Contexts\Operations\KingPerks\Services\KingPerkRequestService;
use App\Contexts\Operations\KingPerks\Services\KingPerkScheduler;
use App\Shared\Infrastructure\Http\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class KingPerkController extends Controller
{
    public function __construct(private readonly PlayerContext $playerContext) {}

    public function index(Request $request, string $event, KingPerkScheduleQuery $query): Response
    {
        $user = $this->authenticated($request);
        $payload = $query->management($this->player(), $event, $request->string('occurrence')->toString() ?: null);

        return Inertia::render('Kingdom/RoyalCourt/Appointments', [
            'user' => ['name' => $user->accountName(), 'email' => $user->accountEmail()],
            ...$payload,
        ]);
    }

    public function my(Request $request, string $event, KingPerkScheduleQuery $query): Response
    {
        $user = $this->authenticated($request);
        $payload = $query->player($this->player(), $event, $request->string('occurrence')->toString() ?: null);

        return Inertia::render('Kingdom/RoyalCourt/MyAppointments', [
            'user' => ['name' => $user->accountName(), 'email' => $user->accountEmail()],
            ...$payload,
        ]);
    }

    public function createPlan(Request $request, string $event, string $occurrence, KingPerkScheduler $scheduler): RedirectResponse
    {
        $this->authenticated($request);
        $scheduler->createPlan($this->player()->playerId, $event, $occurrence);

        return back()->with('status', 'king-perks-plan-created');
    }

    public function publish(Request $request, string $plan, KingPerkScheduler $scheduler): RedirectResponse
    {
        $this->authenticated($request);
        $scheduler->publishPlan($this->player()->playerId, $plan);

        return back()->with('status', 'king-perks-plan-published');
    }

    public function assign(Request $request, string $plan, KingPerkScheduler $scheduler): RedirectResponse
    {
        $this->authenticated($request);
        $validated = $request->validate([
            'appointment_type' => ['required', Rule::enum(KingAppointmentType::class)],
            'player_id' => ['required', 'string'],
            'starts_at' => ['required', 'date_format:Y-m-d\\TH:i'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'appointment_id' => ['nullable', 'string'],
        ]);
        $appointmentId = isset($validated['appointment_id']) ? (string) $validated['appointment_id'] : null;
        $scheduler->assignAppointment(
            actorPlayerId: $this->player()->playerId,
            planId: $plan,
            type: KingAppointmentType::from((string) $validated['appointment_type']),
            targetPlayerId: (string) $validated['player_id'],
            startsAt: CarbonImmutable::parse((string) $validated['starts_at'], 'UTC'),
            notes: $validated['notes'] ?? null,
            appointmentId: $appointmentId,
        );

        return back()->with('status', $appointmentId === null ? 'king-perk-appointed' : 'king-perk-reassigned');
    }

    public function confirm(Request $request, string $appointment, KingPerkScheduler $scheduler): RedirectResponse
    {
        $this->authenticated($request);
        $scheduler->confirmAppointment($this->player()->playerId, $appointment);

        return back()->with('status', 'king-perk-appointment-confirmed');
    }

    public function declineAppointment(Request $request, string $appointment, KingPerkScheduler $scheduler): RedirectResponse
    {
        $this->authenticated($request);
        $scheduler->declineAppointment($this->player()->playerId, $appointment);

        return back()->with('status', 'king-perk-appointment-declined');
    }

    public function activateAppointment(Request $request, string $appointment, KingPerkScheduler $scheduler): RedirectResponse
    {
        $this->authenticated($request);
        $scheduler->markAppointmentActive($this->player()->playerId, $appointment);

        return back()->with('status', 'king-perk-appointment-active');
    }

    public function outcome(Request $request, string $appointment, KingPerkScheduler $scheduler): RedirectResponse
    {
        $this->authenticated($request);
        $validated = $request->validate([
            'status' => ['required', Rule::in([
                KingPerkAppointmentStatus::Completed->value,
                KingPerkAppointmentStatus::NoShow->value,
            ])],
        ]);
        $scheduler->markAppointment(
            $this->player()->playerId,
            $appointment,
            KingPerkAppointmentStatus::from((string) $validated['status']),
        );

        return back()->with('status', 'king-perk-appointment-updated');
    }

    public function replace(Request $request, string $appointment, ReplaceNoShowAppointment $replace): RedirectResponse
    {
        $this->authenticated($request);
        $validated = $request->validate(['player_id' => ['required', 'string']]);
        $replace->handle($this->player()->playerId, $appointment, (string) $validated['player_id']);

        return back()->with('status', 'king-perk-appointment-replaced');
    }

    public function cancelledCooldown(Request $request, string $appointment, KingPerkScheduler $scheduler): RedirectResponse
    {
        $this->authenticated($request);
        $validated = $request->validate(['cancelled_at' => ['nullable', 'date_format:Y-m-d\\TH:i']]);
        $at = isset($validated['cancelled_at'])
            ? CarbonImmutable::parse((string) $validated['cancelled_at'], 'UTC')
            : CarbonImmutable::now('UTC');
        $scheduler->recordCancelledPositionCooldown($this->player()->playerId, $appointment, $at);

        return back()->with('status', 'king-perk-position-cooldown-recorded');
    }

    public function submitRequest(Request $request, string $plan, KingPerkRequestService $requests): RedirectResponse
    {
        $this->authenticated($request);
        $validated = $request->validate([
            'push_category' => ['required', Rule::enum(KingPerkPushCategory::class)],
            'preferred_appointment_type' => ['nullable', Rule::enum(KingAppointmentType::class)],
            'availability_starts_at' => ['required', 'date_format:Y-m-d\\TH:i'],
            'availability_ends_at' => ['required', 'date_format:Y-m-d\\TH:i'],
            'planned_speedup_minutes' => ['nullable', 'integer', 'between:0,5256000'],
            'planned_resource_amount' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $preferred = isset($validated['preferred_appointment_type'])
            ? KingAppointmentType::from((string) $validated['preferred_appointment_type'])
            : null;
        $requests->submit(
            actorPlayerId: $this->player()->playerId,
            planId: $plan,
            category: KingPerkPushCategory::from((string) $validated['push_category']),
            availableFrom: CarbonImmutable::parse((string) $validated['availability_starts_at'], 'UTC'),
            availableUntil: CarbonImmutable::parse((string) $validated['availability_ends_at'], 'UTC'),
            preferredType: $preferred,
            plannedSpeedupMinutes: isset($validated['planned_speedup_minutes']) ? (int) $validated['planned_speedup_minutes'] : null,
            plannedResourceAmount: isset($validated['planned_resource_amount']) ? (int) $validated['planned_resource_amount'] : null,
            notes: $validated['notes'] ?? null,
        );

        return back()->with('status', 'king-perk-request-submitted');
    }

    public function withdrawRequest(Request $request, string $perkRequest, KingPerkRequestService $requests): RedirectResponse
    {
        $this->authenticated($request);
        $requests->withdraw($this->player()->playerId, $perkRequest);

        return back()->with('status', 'king-perk-request-withdrawn');
    }

    public function declineRequest(Request $request, string $perkRequest, KingPerkRequestService $requests): RedirectResponse
    {
        $this->authenticated($request);
        $requests->decline($this->player()->playerId, $perkRequest);

        return back()->with('status', 'king-perk-request-declined');
    }

    public function autoSchedule(Request $request, string $plan, KingPerkAutoScheduler $scheduler): RedirectResponse
    {
        $this->authenticated($request);
        $validated = $request->validate([
            'push_category' => ['required', Rule::enum(KingPerkPushCategory::class)],
            'from' => ['required', 'date_format:Y-m-d\\TH:i'],
            'until' => ['required', 'date_format:Y-m-d\\TH:i'],
            'limit' => ['nullable', 'integer', 'between:1,500'],
        ]);
        $result = $scheduler->handle(
            actorPlayerId: $this->player()->playerId,
            planId: $plan,
            category: KingPerkPushCategory::from((string) $validated['push_category']),
            from: CarbonImmutable::parse((string) $validated['from'], 'UTC'),
            until: CarbonImmutable::parse((string) $validated['until'], 'UTC'),
            limit: isset($validated['limit']) ? (int) $validated['limit'] : 200,
        );

        return back()->with('status', 'king-perks-auto-scheduled:'.$result['assigned']);
    }

    public function planSkill(Request $request, string $plan, KingPerkScheduler $scheduler): RedirectResponse
    {
        $this->authenticated($request);
        $validated = $request->validate([
            'skill_key' => ['required', Rule::enum(KingSkill::class)],
            'planned_activation_at' => ['required', 'date_format:Y-m-d\\TH:i'],
            'effect_duration_minutes' => ['required', 'integer', 'between:1,10080'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $scheduler->planSkill(
            actorPlayerId: $this->player()->playerId,
            planId: $plan,
            skill: KingSkill::from((string) $validated['skill_key']),
            activationAt: CarbonImmutable::parse((string) $validated['planned_activation_at'], 'UTC'),
            effectDurationMinutes: (int) $validated['effect_duration_minutes'],
            notes: $validated['notes'] ?? null,
        );

        return back()->with('status', 'king-perk-skill-planned');
    }

    public function skillScheduled(Request $request, string $skill, KingPerkScheduler $scheduler): RedirectResponse
    {
        $this->authenticated($request);
        $scheduler->markSkillScheduled($this->player()->playerId, $skill);

        return back()->with('status', 'king-perk-skill-scheduled');
    }

    public function skillActivated(Request $request, string $skill, KingPerkScheduler $scheduler): RedirectResponse
    {
        $this->authenticated($request);
        $scheduler->markSkillActivated($this->player()->playerId, $skill);

        return back()->with('status', 'king-perk-skill-activated');
    }

    private function authenticated(Request $request): AuthenticatedAccount
    {
        $user = $request->user();
        abort_unless($user instanceof AuthenticatedAccount, 401);

        return $user;
    }

    private function player(): PlayerReference
    {
        $player = $this->playerContext->playerOrNull();
        abort_unless($player instanceof PlayerReference, 409, 'Select a Player before managing King Perks.');

        return $player;
    }
}
