<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    private const INDEX_NAME = 'payments_method_reference_unique';

    public function up(): void
    {
        $hasEquivalentIndex = collect(Schema::getIndexes('payments'))->contains(
            fn (array $index) => ($index['unique'] ?? false)
                && ($index['columns'] ?? []) === ['method', 'transaction_reference']
        );

        if ($hasEquivalentIndex) {
            return;
        }

        $duplicate = DB::table('payments')
            ->select('method', 'transaction_reference')
            ->groupBy('method', 'transaction_reference')
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if ($duplicate) {
            throw new RuntimeException('Cannot add unique payment references: duplicate references exist for method '.$duplicate->method.'. Resolve them before retrying the migration.');
        }

        Schema::table('payments', function (Blueprint $table): void {
            $table->unique(['method', 'transaction_reference'], self::INDEX_NAME);
        });
    }

    public function down(): void
    {
        if (Schema::hasIndex('payments', self::INDEX_NAME)) {
            Schema::table('payments', function (Blueprint $table): void {
                $table->dropUnique(self::INDEX_NAME);
            });
        }
    }
};