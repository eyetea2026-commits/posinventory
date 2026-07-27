<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // seed roles
        $this->call(RoleSeeder::class);

        $adminRoleId = DB::table('roles')->where('role_name', 'admin')->value('id');
        $cashierRoleId = DB::table('roles')->where('role_name', 'cashier')->value('id');

        // No hardcoded default password — see AdminUserSeeder for the same
        // env-var-or-random-and-print pattern. This seeder is a dev/demo
        // convenience path, but db:seed can be run against any environment
        // by mistake, so it gets the same treatment.
        $adminPassword = env('DEFAULT_ADMIN_PASSWORD') ?: Str::password(16);
        $cashierPassword = env('DEFAULT_CASHIER_PASSWORD') ?: Str::password(16);

        User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make($adminPassword),
            'role_id' => $adminRoleId,
        ]);

        User::factory()->create([
            'name' => 'Cashier User',
            'email' => 'cashier@example.com',
            'password' => Hash::make($cashierPassword),
            'role_id' => $cashierRoleId,
        ]);

        echo "Demo Admin User password: {$adminPassword}\n";
        echo "Demo Cashier User password: {$cashierPassword}\n";
        echo "IMPORTANT: record these now and change them after first login — they will not be shown again.\n";
    }
}
