<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table): void {
            $table->decimal('latitude', 10, 7)->nullable()->after('status');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->decimal('location_accuracy_m', 8, 2)->nullable()->after('longitude');
            $table->timestamp('location_captured_at')->nullable()->after('location_accuracy_m');
            $table->string('source', 20)->default('online')->after('location_captured_at');
        });

        Schema::create('attendance_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attendance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 30);
            $table->string('old_status', 20)->nullable();
            $table->string('new_status', 20)->nullable();
            $table->string('reason', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['attendance_id', 'created_at']);
        });

        Schema::create('attendance_sync_operations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('idempotency_key');
            $table->char('payload_hash', 64);
            $table->string('result_status', 24);
            $table->json('result_payload');
            $table->timestamps();
            $table->unique(['actor_id', 'idempotency_key'], 'attendance_sync_actor_key_unique');
            $table->index(['actor_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_sync_operations');
        Schema::dropIfExists('attendance_audits');

        Schema::table('attendances', function (Blueprint $table): void {
            $table->dropColumn([
                'latitude',
                'longitude',
                'location_accuracy_m',
                'location_captured_at',
                'source',
            ]);
        });
    }
};
