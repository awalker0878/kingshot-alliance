<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Lifecycle\Actions;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Access\Services\AllianceWriteState;
use App\Contexts\Alliance\Lifecycle\Enums\SupportedAllianceLocale;
use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class UpdateAllianceSettings
{
    private const RESERVED_SLUGS = [
        'admin', 'api', 'alliance', 'alliances', 'assistant', 'dashboard', 'events',
        'gift-codes', 'kingdom', 'login', 'logout', 'platform', 'profile', 'public',
        'recruitment', 'register', 'settings',
    ];

    public function __construct(
        private AllianceWriteState $writeState,
        private AllianceAuthorization $authorization,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        string $allianceId,
        string $actorPlayerId,
        string $name,
        string $slug,
        SupportedAllianceLocale $language,
        string $timezone,
    ): string {
        $name = trim($name);
        $slug = Str::slug($slug);

        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Alliance name is required.']);
        }
        if ($slug === '' || in_array($slug, self::RESERVED_SLUGS, true)) {
            throw ValidationException::withMessages(['slug' => 'Choose another Alliance URL name.']);
        }
        if (! in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            throw ValidationException::withMessages(['timezone' => 'Choose a valid IANA timezone.']);
        }

        return DB::transaction(function () use ($allianceId, $actorPlayerId, $name, $slug, $language, $timezone): string {
            $context = $this->writeState->lockExclusiveScope($actorPlayerId, $allianceId);
            $this->authorization->authorizeContext($context, AlliancePermission::Manage);

            if (Alliance::query()
                ->where('slug', $slug)
                ->whereKeyNot($context->alliance->id)
                ->lockForUpdate()
                ->exists()) {
                throw ValidationException::withMessages(['slug' => 'This Alliance URL name is already in use.']);
            }

            $before = [
                'name' => (string) $context->alliance->name,
                'slug' => (string) $context->alliance->slug,
                'language' => (string) $context->alliance->language,
                'timezone' => (string) $context->alliance->timezone,
            ];
            $after = [
                'name' => $name,
                'slug' => $slug,
                'language' => $language->value,
                'timezone' => $timezone,
            ];

            if ($before === $after) {
                return (string) $context->alliance->id;
            }

            $context->alliance->forceFill($after)->save();
            $changes = [];
            foreach ($after as $key => $value) {
                if ($before[$key] !== $value) {
                    $changes[$key] = ['from' => $before[$key], 'to' => $value];
                }
            }

            $metadata = ['changes' => $changes];
            $this->audit->record('alliance.settings_changed', $context->actor, $context->alliance, $context->alliance, $metadata);
            $this->outbox->record('alliance.settings_changed', (string) $context->alliance->id, $context->alliance, $metadata);

            return (string) $context->alliance->id;
        });
    }
}
