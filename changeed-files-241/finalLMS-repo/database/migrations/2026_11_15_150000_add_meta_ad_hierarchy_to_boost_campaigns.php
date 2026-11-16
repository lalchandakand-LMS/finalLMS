<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meta_boost_campaigns', function (Blueprint $table) {
            $table->string('meta_adset_id', 120)->nullable()->after('meta_campaign_id');
            $table->string('meta_creative_id', 120)->nullable()->after('meta_adset_id');
            $table->string('meta_ad_id', 120)->nullable()->after('meta_creative_id');
            $table->string('meta_page_id', 120)->nullable()->after('meta_ad_id');
        });
    }

    public function down(): void
    {
        Schema::table('meta_boost_campaigns', function (Blueprint $table) {
            $table->dropColumn(['meta_adset_id', 'meta_creative_id', 'meta_ad_id', 'meta_page_id']);
        });
    }
};
