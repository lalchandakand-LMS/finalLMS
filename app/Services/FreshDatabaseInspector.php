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

        if ($schema->getTables($database) !== [] || $schema->getViews($database) !== []) {
            throw new RuntimeException('The selected database is not empty. Create a brand-new empty database and try again.');
        }
    }
}
