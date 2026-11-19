<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_page_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('website_page_id')->constrained('website_pages')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('snapshot');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['website_page_id', 'version'], 'website_page_revision_version_unique');
            $table->index(['website_page_id', 'created_at']);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('website_page_revisions') && DB::table('website_page_revisions')->exists()) {
            throw new RuntimeException('Export or explicitly preserve custom page revision history before rolling back this migration.');
        }

        Schema::dropIfExists('website_page_revisions');
    }
};
