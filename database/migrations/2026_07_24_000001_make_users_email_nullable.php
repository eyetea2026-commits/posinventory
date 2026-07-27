<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Email is being dropped from the admin User form — the column must
    // accept NULL for new accounts created without one. The `unique` index
    // is untouched: MySQL allows any number of NULLs under a unique index,
    // so this doesn't conflict with existing users who do have an email.
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
        });
    }
};
