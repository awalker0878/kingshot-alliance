<?php

declare(strict_types=1);

namespace App\Domain\KingPerks\Http\Controllers;

use App\Domain\Events\Models\EventOccurrence;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Kingdoms\Services\PlayerContext;
use App\Domain\KingPerks\Enums\KingAppointmentType;
use App\Domain\KingPerks\Enums\KingPerkAppointmentStatus;
use App\Domain\KingPerks\Enums\KingPerkPushCategory;
use App\Domain\KingPerks\Enums\KingSkill;
use App\Domain\KingPerks\Models\KingPerkAppointment;
use App\Domain\KingPerks\Models\KingPerkPlan;
use App\Domain\KingPerks\Models\KingPerkRequest;
use App\Domain\KingPerks\Models\KingSkillPlan;
use App\Domain\KingPerks\Queries\KingPerkScheduleQuery;
use App\Domain\KingPerks\Services\KingPerkAutoScheduler;
use App\Domain\KingPerks\Services\KingPerkRequestService;
use App\Domain\KingPerks\Services\KingPerkScheduler;
use App\Domain\Platform\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class KingPerkController extends Controller
{
    public function __construct(private readonly PlayerContext $playerContext) {}

    public function index(Request $request, string $event, KingPerkScheduleQuery $query): Response
    {
        $user = $this->user($request);
        $payload = $query->management(
            $this->player(),
            $event,
            $request->string('occurrence')->toString() ?: null,
        );

        return Inertia::render('KingPerks/Manage', [
            'user' => ['name' => (string) $user->name, 'email' => (string) $user->email],
            ...$payload,
        ]);
    }

    public function my(Request $request, string $event, KingPerkScheduleQuery $query): Response
    {
        $user = $this->user($request);
        $payload = $query->player(
            $this->player(),
            $event,
            $request->string('occurrence')->toString() ?: null,
        );

        return Inertia::render('KingPerks/My', [
            'user' => ['name' => (string) $user->name, 'email' => (string) $user->email],
            ...$payload,
        ]);
    }

    public function createPlan(
        Request $request,
        string $event,
        string $occurrence,
        KingPerkScheduler $scheduler,
    ): RedirectResponse {
        $this->user($request);
        $record = EventOccurrence::query()
            ->whereKey($occurrence)
            ->where('event_id', $event)
            ->firstOrFail();
        $scheduler->createPlan($this->player(), $record);

        return back()->with('status', 'king-perks-plan-created');
    }

    public function publish(Request $request, string $plan, KingPerkScheduler $scheduler): RedirectResponse
    {
        $this->user($request);
        $record = KingPerkPlan::query()->whereKey($plan)->firstOrFail();
        $scheduler->publishPlan($this->player(), $record);

        return back()->with('status', 'king-perks-plan-published');
    }

    public function assign(Request $request, string $plan, KingPerkScheduler $scheduler): RedirectResponse
    {
        $this->user($request);
        $validated = $request->validate([
            'appointment_type' => ['required', Rule::enum(KingAppointmentType::class)],
            'player_id' => ['required', 'string'],
            'starts_at' => ['required', 'date_format:Y-m-d\\TH:i'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'appointment_id' => ['nullable', 'string'],
        ]);

        $record = KingPerkPlan::query()->whereKey($plan)->firstOrFail();
        $target = Player::query()->whereKey((string) $validated['player_id'])->firstOrFail();
        $appointment = isset($validated['appointment_id'])
            ? KingPerkAppointment::query()->whereKey((string) $validated['appointment_id'])->firstOrFail()
            : null;

        $scheduler->assignAppointment(
            actor: $this->player(),
            plan: $record,
            type: KingAppointmentType::from((string) $validated['appointment_type']),
            target: $target,
            startsAt: CarbonImmutable::parse((string) $validated['starts_at'], 'UTC'),
            notes: $validated['notes'] ?? null,
            appointment: $appointment,
        );

        return back()->with('status', $appointment === null ? 'king-perk-appointed' : 'king-perk-reassigned');
    }

    public function confirm(Request $request, string $appointment, KingPerkScheduler $scheduler): RedirectResponse
    {
        $this->user($request);
        $record = KingPerkAppointment::query()->whereKey($appointment)->firstOrFail();
        $scheduler->confirmAppointment($this->player(), $record);

        return back()->with('status', 'king-perk-appointment-confirmed');
    }

    public function declineAppointment(Request $request, string $appointment, KingPerkScheduler $scheduler): RedirectResponse
    {
        $this->user($request);
        $record = KingPerkAppointment::query()->whereKey($appointment)->firstOrFail();
        $scheduler->declineAppointment($this->player(), $record);

        return back()->with('status', 'king-perk-appointment-declined');
    }

    public function activateAppointment(Request $request, string $appointment, KingPerkScheduler $scheduler): RedirectResponse
    {
        $this->user($request);
        $record = KingPerkAppointment::query()->whereKey($appointment)->firstOrFail();
        $scheduler->markAppointmentActive($this->player(), $record);

        return back()->with('status', 'king-perk-appointment-active');
    }

    public function outcome(
        Request $request,
        string $appointment,
        KingPerkScheduler $scheduler,
    ): RedirectResponse {
        $this->user($request);
        $validated = $request->validate([
            'status' => ['required', Rule::in([
                KingPerkAppointmentStatus::Completed->value,
                KingPerkAppointmentStatus::NoShow->value,
            ])],
        ]);
        $record = KingPerkAppointment::query()->whereKey($appointment)->firstOrFail();
        $scheduler->markAppointment(
            $this->player(),
            $record,
            KingPerkAppointmentStatus::from((string) $validated['status']),
        );

        return back()->with('status', 'king-perk-appointment-updated');
    }

    public function replace(
        Request $request,
        string $appointment,
        KingPerkScheduler $scheduler,
    ): RedirectResponse {
        $this->user($request);
        $validated = $request->validate([
            'player_id' => ['required', 'string'],
        ]);
        $actor = $this->player();
        $original = KingPerkAppointment::query()->whereKey($appointment)->firstOrFail();
        $target = Player::query()->whereKey((string) $validated['player_id'])->firstOrFail();

        DB::transaction(function () use ($actor, $original, $target, $scheduler): void {
            $current = $scheduler->markAppointment($actor, $original, KingPerkAppointmentStatus::NoShow);
            $plan = KingPerkPlan::query()->whereKey($current->plan_id)->firstOrFail();
            $replacement = $scheduler->assignAppointment(
                actor: $actor,
                plan: $plan,
                type: $current->appointmentType(),
                target: $target,
                startsAt: $current->startsAt(),
                notes: 'Live replacement for no-show appointment '.(string) $current->id,
            );

            $now = CarbonImmutable::now('UTC');
            if (! $now->lt($replacement->startsAt()) && $now->lt($replacement->endsAt())) {
                $scheduler->markAppointmentActive($actor, $replacement);
            }
        });

        return back()->with('status', 'king-perk-appointment-replaced');
    }

    public function cancelledCooldown(
        Request $request,
        string $appointment,
        KingPerkScheduler $scheduler,
    ): RedirectResponse {
        $this->user($request);
        $validated = $request->validate([
            'cancelled_at' => ['nullable', 'date_format:Y-m-d\\TH:i'],
        ]);
        $record = KingPerkAppointment::query()->whereKey($appointment)->firstOrFail();
        $at = isset($validated['cancelled_at'])
            ? CarbonImmutable::parse((string) $validated['cancelled_at'], 'UTC')
            : CarbonImmutable::now('UTC');
        $scheduler->recordCancelledPositionCooldown($this->player(), $record, $at);

        return back()->with('status', 'king-perk-position-cooldown-recorded');
    }

    public function submitRequest(
        Request $request,
        string $plan,
        KingPerkRequestService $requests,
    ): RedirectResponse {
        $this->user($request);
        $validated = $request->validate([
            'push_category' => ['required', Rule::enum(KingPerkPushCategory::class)],
            'preferred_appointment_type' => ['nullable', Rule::enum(KingAppointmentType::class)],
            'availability_starts_at' => ['required', 'date_format:Y-m-d\\TH:i'],
            'availability_ends_at' => ['required', 'date_format:Y-m-d\\TH:i'],
            'planned_speedup_minutes' => ['nullable', 'integer', 'between:0,5256000'],
            'planned_resource_amount' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $record = KingPerkPlan::query()->whereKey($plan)->firstOrFail();
        $preferred = isset($validated['preferred_appointment_type'])
            ? KingAppointmentType::from((string) $validated['preferred_appointment_type'])
            : null;
        $requests->submit(
            actor: $this->player(),
            plan: $record,
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

    public function withdrawRequest(
        Request $request,
        string $perkRequest,
        KingPerkRequestService $requests,
    ): RedirectResponse {
        $this->user($request);
        $record = KingPerkRequest::query()->whereKey($perkRequest)->firstOrFail();
        $requests->withdraw($this->player(), $record);

        return back()->with('status', 'king-perk-request-withdrawn');
    }

    public function declineRequest(
        Request $request,
        string $perkRequest,
        KingPerkRequestService $requests,
    ): RedirectResponse {
        $this->user($request);
        $record = KingPerkRequest::query()->whereKey($perkRequest)->firstOrFail();
        $requests->decline($this->player(), $record);

        return back()->with('status', 'king-perk-request-declined');
    }

    public function autoSchedule(
        Request $request,
        string $plan,
        KingPerkAutoScheduler $scheduler,
    ): RedirectResponse {
        $this->user($request);
        $validated = $request->validate([
            'push_category' => ['required', Rule::enum(KingPerkPushCategory::class)],
            'from' => ['required', 'date_format:Y-m-d\\TH:i'],
            'until' => ['required', 'date_format:Y-m-d\\TH:i'],
            'limit' => ['nullable', 'integer', 'between:1,500'],
        ]);
        $record = KingPerkPlan::query()->whereKey($plan)->firstOrFail();
        $result = $scheduler->handle(
            actor: $this->player(),
            plan: $record,
            category: KingPerkPushCategory::from((string) $validated['push_category']),
            from: CarbonImmutable::parse((string) $validated['from'], 'UTC'),
            until: CarbonImmutable::parse((string) $validated['until'], 'UTC'),
            limit: isset($validated['limit']) ? (int) $validated['limit'] : 200,
        );

        return back()->with('status', 'king-perks-auto-scheduled:'.$result['assigned']);
    }

    public function planSkill(Request $request, string $plan, KingPerkScheduler $scheduler): RedirectResponse
    {
        $this->user($request);
        $validated = $request->validate([
            'skill_key' => ['required', Rule::enum(KingSkill::class)],
            'planned_activation_at' => ['required', 'date_format:Y-m-d\\TH:i'],
            'effect_duration_minutes' => ['required', 'integer', 'between:1,10080'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $record = KingPerkPlan::query()->whereKey($plan)->firstOrFail();
        $scheduler->planSkill(
            actor: $this->player(),
            plan: $record,
            skill: KingSkill::from((string) $validated['skill_key']),
            activationAt: CarbonImmutable::parse((string) $validated['planned_activation_at'], 'UTC'),
            effectDurationMinutes: (int) $validated['effect_duration_minutes'],
            notes: $validated['notes'] ?? null,
        );

        return back()->with('status', 'king-perk-skill-planned');
    }

    public function skillScheduled(Request $request, string $skill, KingPerkScheduler $scheduler): RedirectResponse
    {
        $this->user($request);
        $record = KingSkillPlan::query()->whereKey($skill)->firstOrFail();
        $scheduler->markSkillScheduled($this->player(), $record);

        return back()->with('status', 'king-perk-skill-scheduled');
    }

    public function skillActivated(Request $request, string $skill, KingPerkScheduler $scheduler): RedirectResponse
    {
        $this->user($request);
        $record = KingSkillPlan::query()->whereKey($skill)->firstOrFail();
        $scheduler->markSkillActivated($this->player(), $record);

        return back()->with('status', 'king-perk-skill-activated');
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    private function player(): Player
    {
        return $this->playerContext->player();
    }
}
