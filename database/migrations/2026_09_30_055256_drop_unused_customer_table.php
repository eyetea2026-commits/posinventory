<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Dead table: 0 rows on both dev and production, and no code path anywhere
// ever inserts into it — the only reference was a read-only search endpoint
// (/api/customers/search, now removed) querying a table nothing populates.
// Confirmed during the database/normalization audit.
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('Customer');
    }

    public function down(): void
    {
        Schema::create('Customer', function (Blueprint $table) {
            $table->id('CustomerID');
            $table->string('CustomerName', 150);
            $table->string('ContactNumber', 50)->nullable();
            $table->string('Email', 150)->nullable();
            $table->string('Address', 255)->nullable();
            $table->timestamps();
        });
    }
};
