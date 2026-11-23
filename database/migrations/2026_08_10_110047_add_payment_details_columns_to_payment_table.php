<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Payment', function (Blueprint $table) {
            $table->string('ReferenceNumber', 50)->nullable()->after('PaymentMethod');
            $table->string('BankName', 100)->nullable()->after('ReferenceNumber');
            $table->string('AccountName', 100)->nullable()->after('BankName');
            $table->date('PaymentDate')->nullable()->after('AccountName');
            $table->time('PaymentTime')->nullable()->after('PaymentDate');
            $table->string('Remarks', 500)->nullable()->after('PaymentTime');

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
