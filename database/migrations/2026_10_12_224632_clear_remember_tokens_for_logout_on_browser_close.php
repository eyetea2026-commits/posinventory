<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * "Remember me" was removed so that closing the browser signs the user
     * out. Clearing the stored tokens makes every remember-me cookie issued
     * before that stop working; nobody is signed out of an open session.
     */
    public function up(): void
    {
        DB::table('users')->whereNotNull('remember_token')->update(['remember_token' => null]);
    }

    /**
     * The old tokens can't be restored (and aren't needed).
     */
    public function down(): void
    {
        //
    }
};
