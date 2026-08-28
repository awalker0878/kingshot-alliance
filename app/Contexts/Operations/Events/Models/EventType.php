<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Events\Models;

use App\Contexts\Operations\Events\Enums\EventCategory;
use App\Contexts\Operations\Events\Enums\EventProfileState;
use App\Contexts\Operations\Events\Enums\EventTypeVerificationState;
use App\Contexts\Operations\Events\Enums\EventWorkflowDimension;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $slug
 * @property string $name_key
 * @property string|null $description_key
 * @property EventCategory $category
 * @property EventTypeVerificationState $verification_state
 * @property EventProfileState $profile_state
 * @property string|null $source_label
 * @property string|null $source_reference
 * @property Carbon|null $source_observed_at
 * @property string|null $game_version_boundary
 * @property string|null $icon_key
 * @property bool $is_system
 * @property bool $is_active
 * @property int $sort_order
 * @property-read Collection<int, EventTypeScope> $scopes
 * @property-read Collection<int, EventTypeWorkflowDimension> $workflowDimensions
 */
final class EventType extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'slug',
        'name_key',
        'description_key',
        'category',
        'verification_state',
        'profile_state',
        'source_label',
        'source_reference',
        'source_observed_at',
        'game_version_boundary',
        'icon_key',
        'is_system',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'category' => EventCategory::class,
            'verification_state' => EventTypeVerificationState::class,
            'profile_state' => EventProfileState::class,
            'source_observed_at' => 'immutable_datetime',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(static function (EventType $type): void {
            if ($type->profileStateEnum() !== EventProfileState::Enabled) {
                return;
            }

            if ($type->verificationStateEnum() !== EventTypeVerificationState::Verified) {
                throw new DomainException('An event profile cannot be enabled until the Kingshot event identity is verified.');
            }

            if (trim((string) $type->source_label) === '' || trim((string) $type->source_reference) === '' || $type->source_observed_at === null) {
                throw new DomainException('A verified enabled event profile requires source provenance.');
            }
        });
    }

    public function categoryEnum(): EventCategory
    {
        $value = $this->getAttribute('category');

        return $value instanceof EventCategory ? $value : EventCategory::from((string) $value);
    }

    public function verificationStateEnum(): EventTypeVerificationState
    {
        $value = $this->getAttribute('verification_state');

        return $value instanceof EventTypeVerificationState
            ? $value
            : EventTypeVerificationState::from((string) $value);
    }

    public function profileStateEnum(): EventProfileState
    {
        $value = $this->getAttribute('profile_state');

        return $value instanceof EventProfileState
            ? $value
            : EventProfileState::from((string) $value);
    }

    public function profileEnabled(): bool
    {
        return $this->verificationStateEnum() === EventTypeVerificationState::Verified
            && $this->profileStateEnum() === EventProfileState::Enabled
            && trim((string) $this->source_label) !== ''
            && trim((string) $this->source_reference) !== ''
            && $this->source_observed_at !== null;
    }

    public function supportsWorkflow(EventWorkflowDimension $dimension): bool
    {
        if (! $this->profileEnabled()) {
            return false;
        }

        if ($this->relationLoaded('workflowDimensions')) {
            return $this->workflowDimensions->contains(
                static fn (EventTypeWorkflowDimension $row): bool => $row->dimensionEnum() === $dimension,
            );
        }

        return $this->workflowDimensions()
            ->where('dimension', $dimension->value)
            ->exists();
    }

    /** @return HasMany<EventTypeScope, $this> */
    public function scopes(): HasMany
    {
        return $this->hasMany(EventTypeScope::class);
    }

    /** @return HasMany<EventTypeWorkflowDimension, $this> */
    public function workflowDimensions(): HasMany
    {
        return $this->hasMany(EventTypeWorkflowDimension::class);
    }
}
