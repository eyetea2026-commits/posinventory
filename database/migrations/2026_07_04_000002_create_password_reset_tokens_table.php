<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // The admin forgot-password/OTP flow (AdminAuthController::sendOtp/verifyOtp)
        // reads and writes this table directly, but it was never created for this
        // project's custom schema — every reset attempt crashed with a
        // "table doesn't exist" error.
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('password_reset_tokens');
    }
};
