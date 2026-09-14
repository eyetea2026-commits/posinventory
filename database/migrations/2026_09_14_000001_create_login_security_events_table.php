<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('LoginSecurityEvent', function (Blueprint $table) {
            $table->id('LoginSecurityEventID');
            $table->unsignedBigInteger('UserID');
            $table->string('IPAddress', 45)->nullable();
            $table->text('UserAgent')->nullable();
            $table->string('DeviceSummary', 150)->nullable();
            // The exact session this login created -- lets "deny" end only
            // that one session, never a legitimately newer one.
            $table->string('SessionID', 128)->nullable();
            $table->string('ConfirmationStatus', 20)->default('pending');
            $table->dateTime('LoginAt');
            $table->dateTime('ConfirmedAt')->nullable();
            $table->foreign('UserID')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down()
    {
        Schema::dropIfExists('LoginSecurityEvent');
    }
};
