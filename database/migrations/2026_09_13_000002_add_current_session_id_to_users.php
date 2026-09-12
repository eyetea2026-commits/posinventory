<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // Points at the one `sessions.id` row currently allowed to be
            // authenticated as this user, enforcing one active login session
            // per account. Reuses the existing database session store
            // (SESSION_DRIVER=database, sessions.user_id/last_activity) --
            // no separate session-tracking table needed. Null means no
            // account-holding session right now.
            $table->string('current_session_id', 128)->nullable()->after('remember_token');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('current_session_id');
        });
    }
};
