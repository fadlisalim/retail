<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Email verification is being introduced. Mark all pre-existing accounts as
     * verified so nobody who registered before this change gets locked out.
     */
    public function up(): void
    {
        DB::table('users')->whereNull('email_verified_at')->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        // No-op: we can't know which rows were backfilled.
    }
};
