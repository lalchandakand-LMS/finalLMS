<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('referral_income_rules')) {
            Schema::create('referral_income_rules', function (Blueprint $table): void {
                $table->id();
                $table->string('owner_type', 20)->unique();
                $table->decimal('commission_percent', 5, 2)->default(0);
                $table->decimal('minimum_payout', 12, 2)->default(500);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
            DB::table('referral_income_rules')->insert([
                ['owner_type' => 'student', 'commission_percent' => 5, 'minimum_payout' => 500, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['owner_type' => 'teacher', 'commission_percent' => 10, 'minimum_payout' => 500, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }
        if (!Schema::hasTable('referral_codes')) {
            Schema::create('referral_codes', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
                $table->string('owner_type', 20);
                $table->string('code', 32)->unique();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique('owner_user_id');
            });
        }
        if (!Schema::hasTable('referral_attributions')) {
            Schema::create('referral_attributions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('referrer_user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('referred_user_id')->constrained('users')->cascadeOnDelete()->unique();
                $table->foreignId('referral_code_id')->constrained('referral_codes')->restrictOnDelete();
                $table->string('referrer_type', 20);
                $table->string('status', 20)->default('active');
                $table->timestamp('attributed_at');
                $table->timestamps();
                $table->index(['referrer_user_id', 'status']);
            });
        }
        if (!Schema::hasTable('referral_commissions')) {
            Schema::create('referral_commissions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('attribution_id')->constrained('referral_attributions')->restrictOnDelete();
                $table->foreignId('payment_id')->constrained('payments')->restrictOnDelete()->unique();
                $table->foreignId('referrer_user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('referred_user_id')->constrained('users')->cascadeOnDelete();
                $table->decimal('eligible_amount', 12, 2);
                $table->decimal('commission_percent', 5, 2);
                $table->decimal('commission_amount', 12, 2);
                $table->string('currency', 8)->default('BDT');
                $table->string('status', 24)->default('pending');
                $table->foreignId('payout_request_id')->nullable()->index();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->text('admin_note')->nullable();
                $table->timestamps();
                $table->index(['referrer_user_id', 'status']);
            });
        }
        if (!Schema::hasTable('referral_payout_requests')) {
            Schema::create('referral_payout_requests', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('owner_type', 20);
                $table->decimal('amount', 12, 2);
                $table->string('currency', 8)->default('BDT');
                $table->string('method', 24);
                $table->text('account_details_encrypted');
                $table->string('status', 24)->default('pending');
                $table->text('admin_note')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'status']);
            });
        }
        if (!Schema::hasTable('referral_audit_events')) {
            Schema::create('referral_audit_events', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('event', 80);
                $table->string('subject_id', 80);
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['event', 'subject_id']);
            });
        }
    }

    public function down(): void
    {
        // Preserve financial and audit history when this optional module is deactivated or uninstalled.
    }
};
