<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('staff_payrolls', function (Blueprint $table): void {
            if (! Schema::hasColumn('staff_payrolls', 'basic_salary')) $table->decimal('basic_salary', 12, 2)->default(0);
            if (! Schema::hasColumn('staff_payrolls', 'allowances')) $table->decimal('allowances', 12, 2)->default(0);
            if (! Schema::hasColumn('staff_payrolls', 'overtime_hours')) $table->decimal('overtime_hours', 8, 2)->default(0);
            if (! Schema::hasColumn('staff_payrolls', 'overtime_rate')) $table->decimal('overtime_rate', 12, 2)->default(0);
            if (! Schema::hasColumn('staff_payrolls', 'overtime_amount')) $table->decimal('overtime_amount', 12, 2)->default(0);
        });
        DB::table('staff_payrolls')->where('basic_salary', 0)->update(['basic_salary' => DB::raw('gross_salary')]);
    }

    public function down(): void
    {
        Schema::table('staff_payrolls', function (Blueprint $table): void {
            foreach (['overtime_amount', 'overtime_rate', 'overtime_hours', 'allowances', 'basic_salary'] as $column) {
                if (Schema::hasColumn('staff_payrolls', $column)) $table->dropColumn($column);
            }
        });
    }
};
