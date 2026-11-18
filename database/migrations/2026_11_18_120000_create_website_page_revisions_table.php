<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('website_page_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('website_page_id')->constrained()->cascadeOnDelete();
            $table->json('snapshot');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('label', 120)->default('Saved version');
            $table->timestamps();
            $table->index(['website_page_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_page_revisions');
    }
};
