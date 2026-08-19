<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_items', function (Blueprint $table): void {
            $table->boolean('notify_members')->default(false);
            $table->timestamp('broadcasted_at')->nullable();
            $table->index(
                ['type', 'status', 'notify_members', 'broadcasted_at'],
                'content_broadcast_queue_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('content_items', function (Blueprint $table): void {
            $table->dropIndex('content_broadcast_queue_index');
            $table->dropColumn(['notify_members', 'broadcasted_at']);
        });
    }
};
