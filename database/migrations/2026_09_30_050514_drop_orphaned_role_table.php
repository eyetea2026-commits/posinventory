<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Dead table: 0 rows, zero code references anywhere in the app. The live
// auth system uses `roles`/`users` (Laravel's own convention) — this
// singular `role` table (RoleID/RoleName) is a leftover from an earlier
// prototype that was never wired up, confirmed during the database audit.
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('role');
    }

    public function down(): void
    {
        Schema::create('role', function (Blueprint $table) {
            $table->increments('RoleID');
            $table->string('RoleName', 50);
        });
    }
};
