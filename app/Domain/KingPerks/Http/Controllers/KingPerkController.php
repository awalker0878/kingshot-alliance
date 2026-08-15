<?php

declare(strict_types=1);

namespace App\Domain\KingPerks\Http\Controllers;

use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Kingdoms\Services\PlayerContext;
use App\Domain\KingPerks\Enums\KingAppointmentType;
use App\Domain\KingPerks\Enums\KingPerkAppointmentStatus;
use App\Domain\KingPerks\Enums\KingSkill;
use App\Domain\KingPerks\Models\KingPerkAppointment;
use App\Domain\KingPerks\Models\KingPerkPlan;
use App\Domain\KingPerks\Models\KingSkillPlan;
use App\Domain\KingPerks\Queries\KingPerkScheduleQuery;
use App\Domain\KingPerks\Services\KingPerkScheduler;
use App\Domain\Platform\Http\Controllers\Controller;
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

    public function createPlan(
        Request $request,
        string $event,
        string $occurrence,
        KingPerkScheduler $scheduler,
    ): RedirectResponse {
        $this->user($request);
        $record = \App\Domain\Events\Models\EventOccurrence::query()
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
            startsAt: CarbonImmutable::createFromFormat('Y-m-d\\TH:i', (string) $validated['starts_at'], 'UTC'),
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
            ? CarbonImmutable::createFromFormat('Y-m-d\\TH:i', (string) $validated['cancelled_at'], 'UTC')
            : CarbonImmutable::now('UTC');
        $scheduler->recordCancelledPositionCooldown($this->player(), $record, $at);

        return back()->with('status', 'king-perk-position-cooldown-recorded');
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
            activationAt: CarbonImmutable::createFromFormat('Y-m-d\\TH:i', (string) $validated['planned_activation_at'], 'UTC'),
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
