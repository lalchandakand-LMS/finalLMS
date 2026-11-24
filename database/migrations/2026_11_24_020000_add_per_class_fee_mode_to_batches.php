<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('batches')) {
            return;
        }

        Schema::table('batches', function (Blueprint $table): void {
            if (! Schema::hasColumn('batches', 'fee_calculation_mode')) {
                $table->string('fee_calculation_mode', 20)->default('fixed')->after('fee');
            }
            if (! Schema::hasColumn('batches', 'per_class_fee')) {
                $table->decimal('per_class_fee', 12, 2)->nullable()->after('fee_calculation_mode');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('batches')) {
            return;
        }

        Schema::table('batches', function (Blueprint $table): void {
            $columns = [];
            if (Schema::hasColumn('batches', 'per_class_fee')) {
                $columns[] = 'per_class_fee';
            }
            if (Schema::hasColumn('batches', 'fee_calculation_mode')) {
                $columns[] = 'fee_calculation_mode';
            }
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
