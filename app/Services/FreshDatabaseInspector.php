<?php

namespace App\Services;

use Illuminate\Support\Facades\Schema;
use RuntimeException;

class FreshDatabaseInspector
{
    /**
     * Refuse to run a first-time install against a database containing
     * any tables or views. Only inspect the explicitly selected schema.
     */
    public function assertEmpty(string $connection, string $database): void
    {
        $schema = Schema::connection($connection);
        $tables = $schema->getTables($database);
        $views = $schema->getViews($database);

        if (! self::isEmpty($tables, $views)) {
            throw new RuntimeException('The selected database is not empty. Create a brand-new empty database and try again.');
        }
    }

    /**
     * Keep the empty-schema decision independently testable.
     *
     * @param  array<int, mixed>  $tables
     * @param  array<int, mixed>  $views
     */
    public static function isEmpty(array $tables, array $views): bool
    {
        return $tables === [] && $views === [];
    }
}
