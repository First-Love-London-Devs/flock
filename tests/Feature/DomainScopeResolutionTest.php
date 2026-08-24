<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupType;
use App\Services\DomainScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The resolution path itself, with nothing faked.
 *
 * Every other test of domain confinement calls DomainScope::fake(), which
 * hands over a country name and skips the lookup entirely. That made the
 * suite green while the real path was dead: the table check asked the
 * DEFAULT connection for the domains table, and inside an initialised tenant
 * the default is the tenant database, which does not have one. So groupIds()
 * returned null on every production request, null means "no confinement", and
 * Belgium's public attendance counter listed Basel, Bern, Biel and Geneva.
 *
 * These exercise the lookup for real, so it cannot go quietly dead again.
 */
class DomainScopeResolutionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DomainScope::forget();

        // Tests migrate only the tenant path, so the central table is absent.
        if (! Schema::hasTable('domains')) {
            Schema::create('domains', function ($table) {
                $table->increments('id');
                $table->string('domain');
                $table->string('tenant_id');
                $table->string('scope_group_name')->nullable();
                $table->timestamps();
            });
        }
    }

    protected function tearDown(): void
    {
        DomainScope::forget();
        parent::tearDown();
    }

    /**
     * Inserted rather than created through the model: Domain::create()
     * resolves its tenant relation, and this suite migrates no tenants table.
     */
    private function domainRow(string $domain, ?string $scope): void
    {
        DB::table('domains')->insert([
            'domain' => $domain,
            'tenant_id' => 'go-church',
            'scope_group_name' => $scope,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function belgiumWithAChurch(): Group
    {
        $countryType = GroupType::create([
            'name' => 'Country', 'slug' => 'country', 'level' => 0,
            'tracks_attendance' => false, 'is_active' => true,
        ]);
        $streamType = GroupType::create([
            'name' => 'Stream', 'slug' => 'stream', 'level' => 2,
            'tracks_attendance' => false, 'is_active' => true,
        ]);

        $belgium = Group::create(['name' => 'Belgium', 'group_type_id' => $countryType->id, 'is_active' => true]);
        Group::create(['name' => 'Gospel Experience Service', 'group_type_id' => $streamType->id,
            'parent_id' => $belgium->id, 'is_active' => true]);

        $switzerland = Group::create(['name' => 'Switzerland', 'group_type_id' => $countryType->id, 'is_active' => true]);
        Group::create(['name' => 'Basel', 'group_type_id' => $streamType->id,
            'parent_id' => $switzerland->id, 'is_active' => true]);

        return $belgium;
    }

    /**
     * The regression. Tenancy swaps the default connection to the tenant
     * database mid-request; the domains table is not there and never will be.
     */
    public function test_the_domains_table_is_sought_on_the_central_connection_not_whatever_is_default(): void
    {
        config(['database.connections.tenant' => ['driver' => 'sqlite', 'database' => ':memory:']]);
        config(['database.default' => 'tenant']);

        $this->assertNotSame(
            'tenant',
            DomainScope::centralConnection(),
            'Asking the tenant connection finds no domains table, and this class then disables itself silently.'
        );
    }

    public function test_a_real_lookup_confines_to_the_country_named_on_the_domain(): void
    {
        $belgium = $this->belgiumWithAChurch();

        $this->domainRow('gochurch.church-stack.com', 'Belgium');

        $this->app['request']->headers->set('HOST', 'gochurch.church-stack.com');

        $ids = DomainScope::groupIds();

        $this->assertNotNull($ids, 'A domain naming a country must confine, not fall open.');

        $names = Group::whereIn('id', $ids)->pluck('name');
        $this->assertContains('Belgium', $names);
        $this->assertContains('Gospel Experience Service', $names);
        $this->assertNotContains('Switzerland', $names);
        $this->assertNotContains('Basel', $names, 'This is the leak the admin photographed.');
    }

    public function test_a_domain_naming_no_country_still_narrows_nothing(): void
    {
        $this->belgiumWithAChurch();

        $this->domainRow('fl-eurozone.church-stack.com', null);

        $this->app['request']->headers->set('HOST', 'fl-eurozone.church-stack.com');

        $this->assertNull(DomainScope::groupIds(), 'The group-wide address must keep seeing everything.');
    }

    public function test_an_unknown_domain_narrows_nothing(): void
    {
        $this->belgiumWithAChurch();
        $this->app['request']->headers->set('HOST', 'nobody.church-stack.com');

        $this->assertNull(DomainScope::groupIds());
    }

    /**
     * Renaming a group must not lock a country out of its own address; the
     * documented choice is to fall open rather than return an empty set.
     */
    public function test_a_country_name_matching_no_group_narrows_nothing(): void
    {
        $this->belgiumWithAChurch();

        $this->domainRow('renamed.church-stack.com', 'Belgique');

        $this->app['request']->headers->set('HOST', 'renamed.church-stack.com');

        $this->assertNull(DomainScope::groupIds());
    }

    public function test_it_survives_having_no_central_connection_at_all(): void
    {
        config(['database.connections.nowhere' => ['driver' => 'sqlite', 'database' => '/nonexistent/x.sqlite']]);
        config(['tenancy.database.central_connection' => 'nowhere']);
        config(['database.connections.mysql' => null]);

        $this->app['request']->headers->set('HOST', 'gochurch.church-stack.com');

        // A queue worker with no central database must not take the app down.
        $this->assertNull(DomainScope::groupIds());
    }
}
