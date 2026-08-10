<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Adds the columns Cheque and Bank Transfer payments genuinely need but the
// Payment table never had (it only ever stored PaymentAmount/PaymentMethod/
// ReceiptNumber) — account_number was already being collected client-side
// for GCash/Bank/Cheque but silently discarded server-side since there was
// nowhere to put it. All nullable/additive: existing Cash/GCash rows are
// untouched, no backfill needed.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Payment', function (Blueprint $table) {
            // Unified column: GCash reference / Cheque Number / Bank Transfer
            // reference — one concept ("the instrument's identifying number").
            $table->string('ReferenceNumber', 50)->nullable()->after('PaymentMethod');
            $table->string('BankName', 100)->nullable()->after('ReferenceNumber');
            $table->string('AccountName', 100)->nullable()->after('BankName');
            $table->date('PaymentDate')->nullable()->after('AccountName');
            $table->time('PaymentTime')->nullable()->after('PaymentDate');
            $table->string('Remarks', 500)->nullable()->after('PaymentTime');

            // NULLs are unrestricted under standard unique-index semantics
            // (both MySQL and SQLite), so this doesn't affect Cash rows or
            // GCash rows left without a reference — only actually-duplicate
            // (method, reference) pairs are rejected.
            $table->unique(['PaymentMethod', 'ReferenceNumber'], 'payment_method_reference_unique');
        });
    }

    public function down(): void
    {
        Schema::table('Payment', function (Blueprint $table) {
            $table->dropUnique('payment_method_reference_unique');
            $table->dropColumn(['ReferenceNumber', 'BankName', 'AccountName', 'PaymentDate', 'PaymentTime', 'Remarks']);
        });
    }
};
