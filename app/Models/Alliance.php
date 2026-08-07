<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Identity\Enums\AllianceStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Alliance extends Model
{
    use HasFactory;
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(AllianceMembership::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }
}
