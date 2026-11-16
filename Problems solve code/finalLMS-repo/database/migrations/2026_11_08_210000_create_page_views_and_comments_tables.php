<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('page_views', function (Blueprint $table) {
            $table->id();
            $table->string('view_type', 20);
            $table->string('viewable_type', 120);
            $table->unsignedBigInteger('viewable_id')->default(0);
            $table->char('visitor_hash', 64);
            $table->date('viewed_on');
            $table->timestamps();
            $table->unique(['view_type', 'viewable_type', 'viewable_id', 'visitor_hash', 'viewed_on'], 'page_views_daily_unique');
            $table->index(['view_type', 'viewable_type', 'viewable_id']);
            $table->index('viewed_on');
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->morphs('commentable');
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
        Schema::dropIfExists('page_views');
    }
};
