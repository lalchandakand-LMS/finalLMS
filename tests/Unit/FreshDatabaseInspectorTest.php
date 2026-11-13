<?php

namespace Tests\Unit;

use App\Services\FreshDatabaseInspector;
use Illuminate\Support\Facades\Schema;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class FreshDatabaseInspectorTest extends TestCase
{
    public function test_it_accepts_a_completely_empty_selected_database(): void
    {
        $schema = Mockery::mock();
        Schema::shouldReceive('connection')->once()->with('mysql')->andReturn($schema);
        $schema->shouldReceive('getTables')->once()->with('account_lms')->andReturn([]);
        $schema->shouldReceive('getViews')->once()->with('account_lms')->andReturn([]);

        app(FreshDatabaseInspector::class)->assertEmpty('mysql', 'account_lms');

        $this->assertTrue(true);
    }

    public function test_it_rejects_a_selected_database_with_any_table(): void
    {
        $schema = Mockery::mock();
        Schema::shouldReceive('connection')->once()->with('mysql')->andReturn($schema);
        $schema->shouldReceive('getTables')->once()->with('account_lms')->andReturn([
            ['name' => 'legacy_content', 'schema' => 'account_lms'],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Create a brand-new empty database');

        app(FreshDatabaseInspector::class)->assertEmpty('mysql', 'account_lms');
    }

    public function test_it_rejects_a_selected_database_with_any_view(): void
    {
        $schema = Mockery::mock();
        Schema::shouldReceive('connection')->once()->with('mysql')->andReturn($schema);
        $schema->shouldReceive('getTables')->once()->with('account_lms')->andReturn([]);
        $schema->shouldReceive('getViews')->once()->with('account_lms')->andReturn([
            ['name' => 'legacy_report', 'schema' => 'account_lms'],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Create a brand-new empty database');

        app(FreshDatabaseInspector::class)->assertEmpty('mysql', 'account_lms');
    }
}
