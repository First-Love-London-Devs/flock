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

    public function test_group_factory_creates_record(): void
    {
        $group = \App\Models\Group::factory()->create();
        $this->assertNotNull($group->id);
        $this->assertNotNull($group->group_type_id);
    }

    public function test_leader_factory_creates_with_member(): void
    {
        $leader = \App\Models\Leader::factory()->create();
        $this->assertNotNull($leader->member);
        $this->assertNotNull($leader->member->first_name);
    }
}
