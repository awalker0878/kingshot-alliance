<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_deliveries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('notification_type', 96)->index();
            $table->unsignedBigInteger('recipient_user_id')->index();
            $table->string('player_id', 26)->nullable()->index();
            $table->string('channel', 32);
            $table->string('subject_type', 64)->nullable();
            $table->string('subject_id', 64)->nullable();
            $table->timestampTz('due_at')->index();
            $table->string('status', 16)->default('pending')->index();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->unsignedInteger('max_attempts')->default(5);
            $table->string('idempotency_key', 64)->unique();
            $table->timestampTz('queued_at')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->timestampTz('next_attempt_at')->nullable()->index();
            $table->text('last_error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'due_at']);
            $table->index(['recipient_user_id', 'status']);
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->unsignedBigInteger('recipient_user_id')->index();
            $table->string('player_id', 26)->nullable()->index();
            $table->string('notification_type', 96);
            $table->string('channel', 32);
            $table->boolean('enabled')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(
                ['recipient_user_id', 'player_id', 'notification_type', 'channel'],
                'notification_preferences_recipient_unique',
            );
        });

        $this->migrateLegacyEventDeliveries();
        $this->migrateLegacyKingPerkDeliveries();

        Schema::dropIfExists('king_perk_reminder_deliveries');
        Schema::dropIfExists('event_reminder_deliveries');
    }

    private function migrateLegacyEventDeliveries(): void
    {
        if (! Schema::hasTable('event_reminder_deliveries')) {
            return;
        }

        $rows = DB::table('event_reminder_deliveries as deliveries')
            ->leftJoin('event_reminder_rules as rules', 'rules.id', '=', 'deliveries.rule_id')
            ->select([
                'deliveries.id',
                'deliveries.occurrence_id',
                'deliveries.rule_id',
                'deliveries.recipient_user_id',
                'deliveries.player_id',
                'deliveries.due_at',
                'deliveries.status',
                'deliveries.attempts',
                'deliveries.idempotency_key',
                'deliveries.queued_at',
                'deliveries.sent_at',
                'deliveries.last_error',
                'deliveries.created_at',
                'deliveries.updated_at',
                'rules.channel',
            ])
            ->get();

        foreach ($rows as $row) {
            DB::table('notification_deliveries')->insertOrIgnore([
                'id' => $row->id,
                'notification_type' => 'event.reminder',
                'recipient_user_id' => $row->recipient_user_id,
                'player_id' => $row->player_id,
                'channel' => $row->channel ?? 'in_app',
                'subject_type' => 'event_occurrence',
                'subject_id' => $row->occurrence_id,
                'due_at' => $row->due_at,
                'status' => $row->status,
                'attempt_count' => $row->attempts ?? 0,
                'max_attempts' => 5,
                'idempotency_key' => $row->idempotency_key,
                'queued_at' => $row->queued_at,
                'sent_at' => $row->sent_at,
                'failed_at' => $row->status === 'failed' ? ($row->updated_at ?? now()) : null,
                'next_attempt_at' => null,
                'last_error' => $row->last_error,
                'metadata' => json_encode(['rule_id' => $row->rule_id], JSON_THROW_ON_ERROR),
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
        }
    }

    private function migrateLegacyKingPerkDeliveries(): void
    {
        if (! Schema::hasTable('king_perk_reminder_deliveries')) {
            return;
        }

        $rows = DB::table('king_perk_reminder_deliveries')->get();
        foreach ($rows as $row) {
            $subjectType = $row->appointment_id !== null ? 'king_perk_appointment' : 'king_skill_plan';
            $subjectId = $row->appointment_id ?? $row->skill_plan_id;

            DB::table('notification_deliveries')->insertOrIgnore([
                'id' => $row->id,
                'notification_type' => 'king_perks.reminder',
                'recipient_user_id' => $row->recipient_user_id,
                'player_id' => $row->player_id,
                'channel' => 'in_app',
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'due_at' => $row->due_at,
                'status' => $row->status,
                'attempt_count' => 0,
                'max_attempts' => 5,
                'idempotency_key' => $row->idempotency_key,
                'queued_at' => $row->queued_at,
                'sent_at' => $row->sent_at,
                'failed_at' => null,
                'next_attempt_at' => null,
                'last_error' => null,
                'metadata' => json_encode([
                    'plan_id' => $row->plan_id,
                    'kind' => $row->kind,
                ], JSON_THROW_ON_ERROR),
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notification_deliveries');
    }
};
