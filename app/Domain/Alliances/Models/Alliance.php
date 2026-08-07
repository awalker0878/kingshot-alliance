<?php

declare(strict_types=1);

namespace App\Domain\Alliances\Models;

use App\Domain\Authorization\Models\Role;
use App\Domain\Content\Models\AllianceBrandingMedia;
use App\Domain\Content\Models\AllianceProfile;
use App\Domain\Content\Models\ContentCategory;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Content\Models\MediaAsset;
use App\Domain\Identity\Models\User;
use App\Domain\Memberships\Models\AllianceMembership;

use App\Domain\Alliances\Enums\AllianceStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class Alliance extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'slug',
        'kingdom',
        'language',
        'timezone',
        'status',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => AllianceStatus::class,
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return HasMany<AllianceMembership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(AllianceMembership::class);
    }

    /** @return HasMany<Role, $this> */
    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    /** @return HasOne<AllianceProfile, $this> */
    public function publicProfile(): HasOne
    {
        return $this->hasOne(AllianceProfile::class);
    }

    /** @return HasMany<ContentCategory, $this> */
    public function contentCategories(): HasMany
    {
        return $this->hasMany(ContentCategory::class);
    }

    /** @return HasMany<ContentItem, $this> */
    public function contentItems(): HasMany
    {
        return $this->hasMany(ContentItem::class);
    }

    /** @return HasMany<MediaAsset, $this> */
    public function mediaAssets(): HasMany
    {
        return $this->hasMany(MediaAsset::class);
    }

    /** @return HasMany<AllianceBrandingMedia, $this> */
    public function brandingMedia(): HasMany
    {
        return $this->hasMany(AllianceBrandingMedia::class);
    }
}
