<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ebook_refunds', function (Blueprint $table) {
            $table->string('status', 24)->default('refunded')->after('reason');
            $table->string('gateway_refund_id', 120)->nullable()->unique()->after('status');
            $table->string('gateway_status', 40)->nullable()->after('gateway_refund_id');
            $table->text('failure_reason')->nullable()->after('gateway_status');
        });
    }

    public function down(): void
    {
        if (DB::table('ebook_refunds')->whereNotNull('gateway_refund_id')->orWhereIn('status', ['processing', 'gateway_unknown'])->exists()) {
            throw new RuntimeException('Refusing to drop unresolved eBook gateway refund evidence.');
        }

        Schema::table('ebook_refunds', function (Blueprint $table) {
            $table->dropUnique(['gateway_refund_id']);
            $table->dropColumn(['status', 'gateway_refund_id', 'gateway_status', 'failure_reason']);
        });
    }
};
