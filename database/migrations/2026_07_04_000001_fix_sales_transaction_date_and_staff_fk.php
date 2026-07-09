<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // SalesTransactionDate was DATE-only, so every receipt showed 12:00 AM
        // regardless of when the sale actually happened.
        // Uses Schema::change() (portable across MySQL/SQLite) instead of a raw
        // `ALTER TABLE ... MODIFY` statement, which is MySQL-only syntax and
        // fails against the SQLite in-memory DB used by the test suite.
        Schema::table('SalesTransaction', function (Blueprint $table) {
            $table->dateTime('SalesTransactionDate')->nullable(false)->change();
        });

        // Deleting a Staff record cascaded to delete that staff member's entire
        // sales history (SalesTransaction -> Billing/SalesItem). Sales records
        // should survive the removal of the staff member who made them.
        Schema::table('SalesTransaction', function (Blueprint $table) {
            $table->dropForeign(['StaffID']);
            $table->foreign('StaffID')->references('StaffID')->on('Staff')->onDelete('restrict');
        });
    }

    public function down()
    {
        Schema::table('SalesTransaction', function (Blueprint $table) {
            $table->dropForeign(['StaffID']);
            $table->foreign('StaffID')->references('StaffID')->on('Staff')->onDelete('cascade');
        });

        Schema::table('SalesTransaction', function (Blueprint $table) {
            $table->date('SalesTransactionDate')->nullable(false)->change();
        });
    }
};
