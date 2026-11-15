<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payment_combo_courses', function (Blueprint $table): void {
            $table->foreignId('batch_id')->nullable()->after('course_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payment_combo_courses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('batch_id');
        });
    }
};
