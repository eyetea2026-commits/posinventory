<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Categories are deactivated instead of deleted. Every existing category
     * starts out active.
     */
    public function up(): void
    {
        Schema::table('Category', function (Blueprint $table) {
            $table->boolean('IsActive')->default(true)->after('Description');
        });
    }

    public function down(): void
    {
        Schema::table('Category', function (Blueprint $table) {
            $table->dropColumn('IsActive');
        });
    }
};
