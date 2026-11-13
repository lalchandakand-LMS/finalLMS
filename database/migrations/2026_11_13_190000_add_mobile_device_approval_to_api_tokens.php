<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_devices', function (Blueprint $table): void {
            $table->char('api_challenge_hash', 64)->nullable()->unique();
            $table->timestamp('api_challenge_expires_at')->nullable();
        });

        Schema::table('api_tokens', function (Blueprint $table): void {
            $table->foreignId('student_device_id')->nullable()->after('user_id')
                ->constrained('student_devices')->nullOnDelete();
            $table->index(['user_id', 'student_device_id', 'revoked_at'], 'api_tokens_device_access_idx');
        });
    }

    public function down(): void
    {
        Schema::table('api_tokens', function (Blueprint $table): void {
            $table->dropIndex('api_tokens_device_access_idx');
            $table->dropConstrainedForeignId('student_device_id');
        });

        Schema::table('student_devices', function (Blueprint $table): void {
            $table->dropUnique(['api_challenge_hash']);
            $table->dropColumn(['api_challenge_hash', 'api_challenge_expires_at']);
        });
    }
};
