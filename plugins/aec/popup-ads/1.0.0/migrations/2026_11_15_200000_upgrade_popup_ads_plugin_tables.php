<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('visitor_popups')) {
            Schema::create('visitor_popups', function (Blueprint $table): void {
                $table->id();
                $table->string('title', 180);
                $table->text('body')->nullable();
                $table->string('popup_type', 30)->default('offer')->index();
                $table->foreignId('image_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
                $table->string('button_label', 60)->nullable();
                $table->string('button_url', 500)->nullable();
                $table->string('audience', 20)->default('all')->index();
                $table->string('frequency', 24)->default('once_per_session');
                $table->unsignedSmallInteger('delay_seconds')->default(1);
                $table->boolean('is_active')->default(false)->index();
                $table->timestamp('starts_at')->nullable()->index();
                $table->timestamp('ends_at')->nullable()->index();
                $table->unsignedInteger('priority')->default(0);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        Schema::table('visitor_popups', function (Blueprint $table): void {
            if (! Schema::hasColumn('visitor_popups', 'status')) {
                $table->string('status', 24)->default('draft')->index()->after('is_active');
            }
            if (! Schema::hasColumn('visitor_popups', 'content_mode')) {
                $table->string('content_mode', 24)->default('mixed')->after('popup_type');
            }
            if (! Schema::hasColumn('visitor_popups', 'rich_html')) {
                $table->text('rich_html')->nullable()->after('body');
            }
            if (! Schema::hasColumn('visitor_popups', 'layout')) {
                $table->string('layout', 32)->default('modal')->after('content_mode');
            }
            if (! Schema::hasColumn('visitor_popups', 'placement')) {
                $table->string('placement', 24)->default('center')->after('layout');
            }
            if (! Schema::hasColumn('visitor_popups', 'max_width_px')) {
                $table->unsignedSmallInteger('max_width_px')->default(620)->after('placement');
            }
            if (! Schema::hasColumn('visitor_popups', 'background_color')) {
                $table->string('background_color', 16)->default('#ffffff')->after('max_width_px');
            }
            if (! Schema::hasColumn('visitor_popups', 'text_color')) {
                $table->string('text_color', 16)->default('#14213d')->after('background_color');
            }
            if (! Schema::hasColumn('visitor_popups', 'accent_color')) {
                $table->string('accent_color', 16)->default('#1546a8')->after('text_color');
            }
            if (! Schema::hasColumn('visitor_popups', 'font_scale')) {
                $table->string('font_scale', 16)->default('normal')->after('accent_color');
            }
            if (! Schema::hasColumn('visitor_popups', 'overlay_opacity')) {
                $table->unsignedTinyInteger('overlay_opacity')->default(70)->after('font_scale');
            }
            if (! Schema::hasColumn('visitor_popups', 'show_close_button')) {
                $table->boolean('show_close_button')->default(true)->after('overlay_opacity');
            }
            if (! Schema::hasColumn('visitor_popups', 'dismissible')) {
                $table->boolean('dismissible')->default(true)->after('show_close_button');
            }
            if (! Schema::hasColumn('visitor_popups', 'animation_style')) {
                $table->string('animation_style', 16)->default('fade')->after('dismissible');
            }
            if (! Schema::hasColumn('visitor_popups', 'respect_reduced_motion')) {
                $table->boolean('respect_reduced_motion')->default(true)->after('animation_style');
            }
            if (! Schema::hasColumn('visitor_popups', 'mobile_image_media_id')) {
                $table->foreignId('mobile_image_media_id')->nullable()->after('image_media_id')->constrained('media_assets')->nullOnDelete();
            }
            if (! Schema::hasColumn('visitor_popups', 'hide_on_mobile')) {
                $table->boolean('hide_on_mobile')->default(false)->after('mobile_image_media_id');
            }
            if (! Schema::hasColumn('visitor_popups', 'hide_on_desktop')) {
                $table->boolean('hide_on_desktop')->default(false)->after('hide_on_mobile');
            }
            if (! Schema::hasColumn('visitor_popups', 'target_paths')) {
                $table->text('target_paths')->nullable()->after('audience');
            }
            if (! Schema::hasColumn('visitor_popups', 'target_roles')) {
                $table->json('target_roles')->nullable()->after('target_paths');
            }
            if (! Schema::hasColumn('visitor_popups', 'target_branch_id')) {
                $table->foreignId('target_branch_id')->nullable()->after('target_roles')->constrained('branches')->nullOnDelete();
            }
            if (! Schema::hasColumn('visitor_popups', 'target_course_id')) {
                $table->foreignId('target_course_id')->nullable()->after('target_branch_id')->constrained('courses')->nullOnDelete();
            }
            if (! Schema::hasColumn('visitor_popups', 'target_batch_id')) {
                $table->foreignId('target_batch_id')->nullable()->after('target_course_id')->constrained('batches')->nullOnDelete();
            }
            if (! Schema::hasColumn('visitor_popups', 'timezone')) {
                $table->string('timezone', 64)->default('UTC')->after('ends_at');
            }
            if (! Schema::hasColumn('visitor_popups', 'scroll_trigger_percent')) {
                $table->unsignedTinyInteger('scroll_trigger_percent')->default(0)->after('delay_seconds');
            }
            if (! Schema::hasColumn('visitor_popups', 'cooldown_hours')) {
                $table->unsignedSmallInteger('cooldown_hours')->default(0)->after('scroll_trigger_percent');
            }
            if (! Schema::hasColumn('visitor_popups', 'max_impressions')) {
                $table->unsignedInteger('max_impressions')->nullable()->after('cooldown_hours');
            }
            if (! Schema::hasColumn('visitor_popups', 'analytics_enabled')) {
                $table->boolean('analytics_enabled')->default(true)->after('max_impressions');
            }
            if (! Schema::hasColumn('visitor_popups', 'require_consent_for_analytics')) {
                $table->boolean('require_consent_for_analytics')->default(true)->after('analytics_enabled');
            }
        });

        if (Schema::hasColumn('visitor_popups', 'status')) {
            DB::table('visitor_popups')
                ->where('is_active', true)
                ->where('status', 'draft')
                ->update(['status' => 'active']);
        }

        if (! Schema::hasTable('popup_ad_events')) {
            Schema::create('popup_ad_events', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('popup_id')->constrained('visitor_popups')->cascadeOnDelete();
                $table->date('event_date');
                $table->unsignedInteger('impressions')->default(0);
                $table->unsignedInteger('clicks')->default(0);
                $table->unsignedInteger('dismissals')->default(0);
                $table->timestamps();

                $table->unique(['popup_id', 'event_date'], 'popup_ad_events_popup_date_unique');
            });
        }
    }

    public function down(): void
    {
        $hasCampaigns = Schema::hasTable('visitor_popups') && DB::table('visitor_popups')->exists();
        $hasEvents = Schema::hasTable('popup_ad_events') && DB::table('popup_ad_events')->exists();

        if ($hasCampaigns || $hasEvents) {
            throw new \RuntimeException('Popup campaign or analytics data exists. Export or explicitly purge retained popup data before rolling back this migration.');
        }

        Schema::dropIfExists('popup_ad_events');
    }
};
