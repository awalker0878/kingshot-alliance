<?php

declare(strict_types=1);

namespace App\Contexts\Platform\AllianceAdministration\Models;

use Illuminate\Database\Eloquent\Model;

final class AlliancePlanAssignment extends Model
{
    protected $table = 'alliance_plan_assignments';

    protected $primaryKey = 'alliance_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['alliance_id', 'plan_code', 'assigned_by_user_id', 'assigned_at'];

    protected function casts(): array
    {
        return [
            'assigned_by_user_id' => 'integer',
            'assigned_at' => 'datetime',
        ];
    }
}
