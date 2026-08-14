<?php

declare(strict_types=1);

namespace App\Domain\Events\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class EventPollOption extends Model
{
    use HasUlids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['poll_id', 'label', 'value', 'sort_order', 'metadata'];
    protected function casts(): array { return ['sort_order' => 'integer', 'metadata' => 'array']; }
    public function poll(): BelongsTo { return $this->belongsTo(EventPoll::class, 'poll_id'); }
    public function votes(): HasMany { return $this->hasMany(EventPollVote::class, 'option_id'); }
}
