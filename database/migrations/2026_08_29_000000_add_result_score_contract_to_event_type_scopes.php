<?php

declare(strict_types=1);

use App\Contexts\Operations\Events\Catalog\KingShotEventTypeCatalog;
use App\Contexts\Operations\Events\Enums\EventProfileState;
use App\Contexts\Operations\Events\Enums\EventTypeVerificationState;
use App\Contexts\Operations\Events\Enums\EventWorkflowDimension;
use App\Contexts\Operations\Results\Catalog\KingShotEventMetricCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_type_scopes', function (Blueprint $table): void {
            $table->string('result_score_label_key', 180)->nullable();
            $table->string('result_score_unit', 64)->nullable();
            $table->boolean('result_score_higher_is_better')->nullable();
        });

        foreach (KingShotEventTypeCatalog::definitions() as $definition) {
            if ($definition['verification_state'] !== EventTypeVerificationState::Verified
                || $definition['profile_state'] !== EventProfileState::Enabled
                || ! in_array(EventWorkflowDimension::Results, $definition['workflow_dimensions'], true)) {
                continue;
            }

            $typeId = DB::table('event_types')->where('slug', $definition['slug'])->value('id');
            if (! is_string($typeId)) {
                throw new \RuntimeException('Missing persisted Event Type '.$definition['slug'].'.');
            }

            foreach ($definition['scopes'] as $scope) {
                $score = KingShotEventMetricCatalog::profile($definition['slug'], $scope['scope'])['score'];
                if ($score === null) {
                    continue;
                }

                DB::table('event_type_scopes')
                    ->where('event_type_id', $typeId)
                    ->where('scope', $scope['scope']->value)
                    ->update([
                        'result_score_label_key' => $score['label_key'],
                        'result_score_unit' => $score['unit'],
                        'result_score_higher_is_better' => $score['higher_is_better'],
                    ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('event_type_scopes', function (Blueprint $table): void {
            $table->dropColumn([
                'result_score_label_key',
                'result_score_unit',
                'result_score_higher_is_better',
            ]);
        });
    }
};
