<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('download_permissions', function (Blueprint $table) {
            $table->foreignId('revoked_by')->nullable()->after('revoked_at')->constrained('users')->nullOnDelete();
            $table->text('revocation_reason')->nullable()->after('revoked_by');
        });

        Schema::create('ebook_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete()->unique();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('refund_reference', 120)->unique();
            $table->text('reason');
            $table->timestamp('processed_at');
            $table->timestamps();
            $table->index('processed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ebook_refunds');
        Schema::table('download_permissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('revoked_by');
            $table->dropColumn('revocation_reason');
        });
    }
};
