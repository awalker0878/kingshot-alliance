<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Models;

use App\Contexts\Alliance\Core\Models\Alliance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AllianceProfile extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'alliance_id';

    protected $keyType = 'string';

    protected $fillable = [
        'alliance_id',
        'description',
        'primary_color',
    ];

    /** @return BelongsTo<Alliance, $this> */
    public function alliance(): BelongsTo
    {
        return $this->belongsTo(Alliance::class);
    }
}
