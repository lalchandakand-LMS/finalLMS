<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mfs_merchant_configurations', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 24)->unique();
            $table->string('mode', 16)->default('sandbox');
            $table->longText('credentials');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('mfs_merchant_configurations') && DB::table('mfs_merchant_configurations')->exists()) {
            throw new RuntimeException('MFS merchant credentials exist; export and clear them before rolling back this migration.');
        }
        Schema::dropIfExists('mfs_merchant_configurations');
    }
};
