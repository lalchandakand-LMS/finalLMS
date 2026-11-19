<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_tracking_risk_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('risk_code', 40);
            $table->string('action', 20);
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['student_id', 'risk_code', 'id'], 'student_tracking_risk_state_idx');
            $table->index(['actor_id', 'created_at'], 'student_tracking_risk_actor_idx');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('student_tracking_risk_actions')
            && DB::table('student_tracking_risk_actions')->exists()) {
            throw new RuntimeException(
                'Student tracking risk-action audit records exist. Export or explicitly preserve them before rolling back this migration.'
            );
        }

        Schema::dropIfExists('student_tracking_risk_actions');
    }
};
