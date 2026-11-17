<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

return new class extends Migration
{
    public function up(): void
    {
        $requiredTables = [
            'flash_sales',
            'flash_sale_course',
            'flash_sale_course_category',
            'payments',
            'enrollments',
        ];

        foreach ($requiredTables as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException("Flash Sale requires the LMS shared {$table} schema. Run core LMS migrations first.");
            }
        }

        foreach ([
            ['payments', 'flash_sale_id'],
            ['enrollments', 'flash_sale_id'],
        ] as [$table, $column]) {
            if (! Schema::hasColumn($table, $column)) {
                throw new RuntimeException("Flash Sale requires the shared {$table}.{$column} reference.");
            }
        }
    }

    public function down(): void
    {
        // Campaign, redemption and payment history is owned by the LMS shared
        // schema. Plugin uninstall must never delete or rewrite that data.
    }
};
