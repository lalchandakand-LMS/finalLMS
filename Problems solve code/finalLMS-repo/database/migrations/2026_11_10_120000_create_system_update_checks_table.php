<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_update_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 24);
            $table->string('current_version', 32);
            $table->string('target_version', 32)->nullable();
            $table->char('manifest_sha256', 64)->nullable();
            $table->char('git_commit', 40)->nullable();
            $table->text('message');
            $table->json('details')->nullable();
            $table->timestamp('checked_at');
            $table->timestamps();

            $table->index(['status', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_update_checks');
    }
};
