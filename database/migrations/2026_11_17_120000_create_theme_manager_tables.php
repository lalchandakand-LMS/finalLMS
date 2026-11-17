<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('themes', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80);
            $table->string('version', 40);
            $table->string('name', 120);
            $table->string('author', 120)->nullable();
            $table->string('description', 500)->nullable();
            $table->string('min_lms_version', 40)->default('1.0.0');
            $table->json('manifest');
            $table->json('assets');
            $table->string('preview_path', 500)->nullable();
            $table->string('package_path', 500);
            $table->char('package_sha256', 64);
            $table->foreignId('installed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['slug', 'version']);
            $table->index(['slug', 'created_at']);
        });

        Schema::create('theme_activations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('theme_id')->nullable()->constrained('themes')->nullOnDelete();
            $table->string('action', 20);
            $table->json('previous_state')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('theme_activations');
        Schema::dropIfExists('themes');
    }
};
