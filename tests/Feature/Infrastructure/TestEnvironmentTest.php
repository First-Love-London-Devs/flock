<?php

namespace Tests\Feature\Infrastructure;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TestEnvironmentTest extends TestCase
{
    public function test_tenant_tables_are_migrated(): void
    {
        $this->assertTrue(Schema::hasTable('groups'));
        $this->assertTrue(Schema::hasTable('members'));
        $this->assertTrue(Schema::hasTable('leaders'));
        $this->assertTrue(Schema::hasTable('attendance_summaries'));
        $this->assertTrue(Schema::hasTable('role_definitions'));
        $this->assertTrue(Schema::hasTable('leader_roles'));
    }
}
