<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_boost_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('title', 180);
            $table->text('post_url')->nullable();
            $table->text('destination_url');
            $table->string('objective', 30)->default('traffic');
            $table->text('audience_description')->nullable();
            $table->string('target_location', 160)->nullable();
            $table->decimal('daily_budget_bdt', 10, 2);
            $table->decimal('total_budget_bdt', 10, 2)->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->string('meta_campaign_id', 120)->nullable();
            $table->decimal('actual_spend_bdt', 10, 2)->default(0);
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->unsignedBigInteger('leads')->default(0);
            $table->unsignedBigInteger('conversions')->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_boost_campaigns');
    }
};
