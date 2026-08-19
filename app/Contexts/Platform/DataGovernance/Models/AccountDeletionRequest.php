<?php

declare(strict_types=1);

namespace App\Contexts\Platform\DataGovernance\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $user_id
 * @property string $status
 * @property Carbon $requested_at
 * @property Carbon $eligible_at
 * @property Carbon|null $processed_at
 * @property string|null $blocked_reason
 */
final class AccountDeletionRequest extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['user_id', 'status', 'requested_at', 'eligible_at', 'processed_at', 'blocked_reason'];

    protected function casts(): array
    {
        return ['requested_at' => 'datetime', 'eligible_at' => 'datetime', 'processed_at' => 'datetime'];
    }
}
