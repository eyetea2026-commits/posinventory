<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A supplier can have a mobile and a landline ("0917-533-7520 / (02)
     * 8929-9911"), and may have no email.
     */
    public function up(): void
    {
        Schema::table('Supplier', function (Blueprint $table) {
            $table->string('ContactNumber', 50)->change();
            $table->string('Email', 100)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('Supplier', function (Blueprint $table) {
            $table->string('ContactNumber', 20)->change();
            $table->string('Email', 100)->nullable(false)->change();
        });
    }
};
