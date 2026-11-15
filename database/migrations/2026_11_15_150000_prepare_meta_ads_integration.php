<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // OAuth credentials are stored encrypted in the existing settings table.
        // This migration is intentionally a no-op so deployment remains backwards compatible.
    }

    public function down(): void
    {
        // No schema changes.
    }
};
