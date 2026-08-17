<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Lifecycle\Services;

use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use Illuminate\Support\Facades\DB;

final class AllianceBootstrapProvisioner
{
    public function provision(Alliance $alliance): void
    {
        $now = now();
        DB::table('alliance_plan_assignments')->upsert([['alliance_id'=>$alliance->id,'plan_code'=>'standard','assigned_at'=>$now,'created_at'=>$now,'updated_at'=>$now]], ['alliance_id'], ['plan_code','assigned_at','updated_at']);
        DB::table('alliance_platform_settings')->upsert([['alliance_id'=>$alliance->id,'retention_days'=>30,'queue_partition'=>'standard','api_access_enabled'=>true,'webhooks_enabled'=>true,'created_at'=>$now,'updated_at'=>$now]], ['alliance_id'], ['retention_days','queue_partition','api_access_enabled','webhooks_enabled','updated_at']);
    }
}
