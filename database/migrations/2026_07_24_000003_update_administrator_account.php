<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    // SECURITY: this migration originally hardcoded a real admin email and a
    // plaintext password directly in source (a committed credential leak —
    // see the 2026-07-26 security audit). It has already run against every
    // environment that needed it; neutralizing up() to a no-op prevents a
    // future fresh install / migrate:fresh from ever silently reintroducing
    // that old, now-compromised password. If you're bootstrapping a new
    // environment, set the Administrator's email/password via
    // `php artisan db:seed --class=AdminUserSeeder` (which reads
    // DEFAULT_ADMIN_PASSWORD from the environment, or generates and prints a
    // random one) or by editing the account directly after creation.
    public function up(): void
    {
        // Intentionally empty.
    }

    public function down(): void
    {
        // Intentionally empty — nothing to revert.
    }
};
