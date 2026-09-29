<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The application already treats Product<->Inventory as strictly 1:1
// (Product::inventory() is a hasOne, and every Inventory row is created
// paired to exactly one product) but the database never enforced it --
// nothing stopped a second Inventory row being inserted for the same
// ProductID. Confirmed zero existing duplicates on both dev and
// production before adding this.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Inventory', function (Blueprint $table) {
            $table->unique('ProductID');
        });
    }

    public function down(): void
    {
        Schema::table('Inventory', function (Blueprint $table) {
            $table->dropUnique(['ProductID']);
        });
    }
};
