<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_classes', function (Blueprint $table) {
            $table->string('recording_url', 2048)->nullable()->after('meeting_url');
            $table->dateTime('recording_published_at')->nullable()->after('recording_url');
            $table->text('recording_notes')->nullable()->after('recording_published_at');
            $table->index(['status', 'recording_published_at']);
        });
    }

    public function down(): void
    {
        Schema::table('live_classes', function (Blueprint $table) {
            $table->dropIndex(['status', 'recording_published_at']);
            $table->dropColumn(['recording_url', 'recording_published_at', 'recording_notes']);
        });
    }
};
