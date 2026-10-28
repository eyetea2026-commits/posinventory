<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

// Replacement (returns are refund-only) and the first Role table (the app
// reads `roles`) were dropped because nothing uses them.
class DropUnusedTablesTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_10_29_001608_drop_unused_replacement_and_role_tables.php');
    }

    public function test_the_unused_tables_are_gone_and_roles_stays(): void
    {
        $this->assertFalse(Schema::hasTable('Replacement'));
        $this->assertFalse(Schema::hasTable('Role'));
        $this->assertTrue(Schema::hasTable('roles'));
        $this->assertTrue(Schema::hasTable('SalesReturn'));
    }

    public function test_a_table_that_still_has_records_is_not_dropped(): void
    {
        Schema::create('Role', function (Blueprint $table) {
            $table->increments('RoleID');
            $table->string('RoleName', 50);
        });
        DB::table('Role')->insert(['RoleName' => 'admin']);

        try {
            $this->migration()->up();
            $this->fail('The migration dropped a table that still had records.');
        } catch (\RuntimeException $e) {
            $this->assertSame('The Role table still has records; it was not dropped.', $e->getMessage());
        }

        $this->assertSame(1, DB::table('Role')->count());
    }

    public function test_rolling_back_brings_both_tables_back(): void
    {
        $this->migration()->down();

        $this->assertTrue(Schema::hasColumns('Replacement', ['ReplacementID', 'SalesReturnID', 'ReplacementProductID', 'Quantity', 'ProcessedBy', 'ReplacementDate', 'SlipNumber', 'Notes']));
        $this->assertTrue(Schema::hasColumns('Role', ['RoleID', 'RoleName']));

        $this->migration()->up();
        $this->assertFalse(Schema::hasTable('Replacement'));
        $this->assertFalse(Schema::hasTable('Role'));
    }
}
