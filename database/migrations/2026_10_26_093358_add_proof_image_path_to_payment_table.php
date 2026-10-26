<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Optional proof of payment photo for GCash, Bank Transfer and Cheque
     * sales (path on the private disk, see App\Services\PaymentProof).
     */
    public function up(): void
    {
        Schema::table('Payment', function (Blueprint $table) {
            $table->string('ProofImagePath', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('Payment', function (Blueprint $table) {
            $table->dropColumn('ProofImagePath');
        });
    }
};
