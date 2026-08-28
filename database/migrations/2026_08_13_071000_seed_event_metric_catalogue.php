<?php

declare(strict_types=1);

use App\Contexts\Operations\Events\Catalog\KingShotEventTypeCatalog;
use App\Contexts\Operations\Events\Enums\EventProfileState;
use App\Contexts\Operations\Events\Enums\EventTypeVerificationState;
use App\Contexts\Operations\Events\Enums\EventWorkflowDimension;
use App\Contexts\Operations\Results\Catalog\KingShotEventMetricCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach (KingShotEventTypeCatalog::definitions() as $definition) {
            if ($definition['verification_state'] !== EventTypeVerificationState::Verified
                || $definition['profile_state'] !== EventProfileState::Enabled
                || ! in_array(EventWorkflowDimension::Results, $definition['workflow_dimensions'], true)) {
                continue;
            }

            $typeId = DB::table('event_types')
                ->where('slug', $definition['slug'])
                ->value('id');

            if (! is_string($typeId)) {
                throw new RuntimeException('Missing persisted Event Type '.$definition['slug'].'.');
            }

            foreach ($definition['scopes'] as $scope) {
                $scopeId = DB::table('event_type_scopes')
                    ->where('event_type_id', $typeId)
                    ->where('scope', $scope['scope']->value)
                    ->value('id');

                if (! is_string($scopeId)) {
                    throw new RuntimeException('Missing persisted Event Type scope '.$definition['slug'].':'.$scope['scope']->value.'.');
                }

                $profile = KingShotEventMetricCatalog::profile(
                    $definition['slug'],
                    $scope['scope'],
                );

                foreach ($profile['metrics'] as $metric) {
                    DB::table('event_metric_definitions')->insert([
                        'id' => (string) Str::ulid(),
                        'event_type_scope_id' => $scopeId,
                        'key' => $metric['key'],
                        'subject' => $metric['subject']->value,
                        'label_key' => $metric['label_key'],
                        'unit' => $metric['unit'],
                        'value_type' => $metric['value_type']->value,
                        'aggregation' => $metric['aggregation']->value,
                        'dimension_kind' => $metric['dimension_kind'],
                        'is_primary' => $metric['is_primary'],
                        'is_contribution_metric' => $metric['is_contribution_metric'],
                        'higher_is_better' => $metric['higher_is_better'],
                        'sort_order' => $metric['sort_order'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        $systemTypeIds = DB::table('event_types')
            ->where('is_system', true)
            ->pluck('id');
        $systemScopeIds = DB::table('event_type_scopes')
            ->whereIn('event_type_id', $systemTypeIds)
            ->pluck('id');

        DB::table('event_metric_definitions')
            ->whereIn('event_type_scope_id', $systemScopeIds)
            ->delete();
    }
};
