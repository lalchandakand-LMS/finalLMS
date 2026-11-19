<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'admin_totp_secret')) {
                $table->text('admin_totp_secret')->nullable();
            }
            if (! Schema::hasColumn('users', 'admin_totp_enabled_at')) {
                $table->timestamp('admin_totp_enabled_at')->nullable();
            }
            if (! Schema::hasColumn('users', 'admin_totp_recovery_codes')) {
                $table->json('admin_totp_recovery_codes')->nullable();
            }
            if (! Schema::hasColumn('users', 'admin_totp_last_used_step')) {
                $table->unsignedBigInteger('admin_totp_last_used_step')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $columns = array_filter([
                Schema::hasColumn('users', 'admin_totp_secret') ? 'admin_totp_secret' : null,
                Schema::hasColumn('users', 'admin_totp_enabled_at') ? 'admin_totp_enabled_at' : null,
                Schema::hasColumn('users', 'admin_totp_recovery_codes') ? 'admin_totp_recovery_codes' : null,
                Schema::hasColumn('users', 'admin_totp_last_used_step') ? 'admin_totp_last_used_step' : null,
            ]);
            if ($columns !== []) {
                $table->dropColumn(array_values($columns));
            }
        });
    }
};
