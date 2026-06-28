<?php

namespace Tests\Feature\Imports;

use App\Filament\Imports\MemberImporter;
use App\Models\Member;
use Filament\Actions\Imports\Importer;
use ReflectionClass;
use Tests\TestCase;

class MemberImporterTest extends TestCase
{
    /** Drive resolveRecord() in isolation by injecting the row data Filament would set. */
    private function resolve(array $data): ?Member
    {
        $importer = (new ReflectionClass(MemberImporter::class))->newInstanceWithoutConstructor();
        $prop = (new ReflectionClass(Importer::class))->getProperty('data');
        $prop->setAccessible(true);
        $prop->setValue($importer, $data);

        return $importer->resolveRecord();
    }

    public function test_it_restores_a_soft_deleted_member_matched_by_email(): void
    {
        $member = Member::factory()->create(['email' => 'gone@example.com']);
        $member->delete();

        $resolved = $this->resolve(['email' => 'GONE@example.com', 'first_name' => 'Re', 'last_name' => 'Turn']);

        $this->assertTrue($resolved->exists);
        $this->assertSame($member->id, $resolved->id);
        $this->assertFalse($resolved->trashed(), 'a trashed email match must be restored, not left deleted');
    }

    public function test_it_restores_a_soft_deleted_member_matched_by_name_and_phone(): void
    {
        $member = Member::factory()->create(['first_name' => 'Blank', 'last_name' => 'Mailer', 'email' => 'x@example.com', 'phone_number' => '0470123']);
        $member->delete();

        $resolved = $this->resolve(['email' => '', 'first_name' => 'Blank', 'last_name' => 'Mailer', 'phone_number' => '0470123']);

        $this->assertSame($member->id, $resolved->id);
        $this->assertFalse($resolved->trashed());
    }

    public function test_it_prefers_a_live_namesake_over_a_trashed_one(): void
    {
        $trashed = Member::factory()->create(['first_name' => 'Sam', 'last_name' => 'Twin', 'email' => 't@example.com']);
        $trashed->delete();
        $live = Member::factory()->create(['first_name' => 'Sam', 'last_name' => 'Twin', 'email' => 'live@example.com']);

        $resolved = $this->resolve(['email' => '', 'first_name' => 'Sam', 'last_name' => 'Twin', 'phone_number' => '']);

        $this->assertSame($live->id, $resolved->id);
        $this->assertFalse($resolved->trashed());
    }

    public function test_it_returns_a_new_member_when_nothing_matches(): void
    {
        $resolved = $this->resolve(['email' => 'fresh@example.com', 'first_name' => 'Fresh', 'last_name' => 'Face']);

        $this->assertFalse($resolved->exists);
    }
}
