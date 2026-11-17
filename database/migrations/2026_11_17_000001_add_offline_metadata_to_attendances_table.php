<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table): void {
            $table->uuid('idempotency_key')->nullable()->unique();
            $table->decimal('location_latitude', 10, 7)->nullable();
            $table->decimal('location_longitude', 10, 7)->nullable();
            $table->decimal('location_accuracy_meters', 8, 2)->nullable();
            $table->timestamp('location_captured_at')->nullable();
        });

        Schema::create('attendance_change_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attendance_id')->constrained('attendances')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('previous_status', 20)->nullable();
            $table->string('new_status', 20);
            $table->string('reason', 500)->nullable();
            $table->string('source', 32)->default('web');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['attendance_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_change_logs');

        Schema::table('attendances', function (Blueprint $table): void {
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn([
                'idempotency_key',
                'location_latitude',
                'location_longitude',
                'location_accuracy_meters',
                'location_captured_at',
            ]);
        });
    }
};
