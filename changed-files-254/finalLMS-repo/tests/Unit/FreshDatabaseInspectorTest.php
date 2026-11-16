<?php

namespace Tests\Unit;

use App\Services\FreshDatabaseInspector;
use PHPUnit\Framework\TestCase;

class FreshDatabaseInspectorTest extends TestCase
{
    public function test_it_accepts_a_completely_empty_selected_database(): void
    {
        $this->assertTrue(FreshDatabaseInspector::isEmpty([], []));
    }

    public function test_it_rejects_a_selected_database_with_any_table(): void
    {
        $this->assertFalse(FreshDatabaseInspector::isEmpty([
            ['name' => 'legacy_content', 'schema' => 'account_lms'],
        ], []));
    }

    public function test_it_rejects_a_selected_database_with_any_view(): void
    {
        $this->assertFalse(FreshDatabaseInspector::isEmpty([], [
            ['name' => 'legacy_report', 'schema' => 'account_lms'],
        ]));
    }
}
