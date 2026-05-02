# Bishop & Governor Backend Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Wire the 12 mobile-app endpoints under `governor.*` and `bishop.*` (declared in `~/Projects/flock/app/lib/api.ts`) to real backend data, replacing the existing mock layer. Read-only; seeders only; no admin UI.

**Architecture:** Two role-namespaced controllers (`GovernorController`, `BishopController`) delegate to a single `ConstituencyAnalytics` service that owns all aggregation logic. Sunday/Midweek is derived from `AttendanceSummary.date->dayOfWeek` — no schema change. New `bishop` and `governor` role definitions and a new `Constituency` GroupType are added via seeders. Existing role/group hierarchy untouched.

**Tech Stack:** Laravel 11, `stancl/tenancy`, PHPUnit, SQLite for tests, Eloquent factories.

**Spec:** `docs/superpowers/specs/2026-05-02-bishop-governor-backend-design.md`

---

## File Structure

**New files:**

| Path | Responsibility |
|---|---|
| `tests/TestCase.php` (rewrite) | Base test case extending Laravel's, adding tenant-migration runner + `RefreshDatabase` helper |
| `tests/Concerns/BuildsGovernanceFixtures.php` | Trait providing `makeConstituency()`, `makeCellGroup()`, `submitAttendance()` helpers used across analytics tests |
| `database/factories/GroupTypeFactory.php` | Factory for `GroupType` |
| `database/factories/GroupFactory.php` | Factory for `Group` |
| `database/factories/MemberFactory.php` | Factory for `Member` |
| `database/factories/LeaderFactory.php` | Factory for `Leader` |
| `database/factories/RoleDefinitionFactory.php` | Factory for `RoleDefinition` |
| `database/factories/LeaderRoleFactory.php` | Factory for `LeaderRole` |
| `database/factories/AttendanceSummaryFactory.php` | Factory for `AttendanceSummary` |
| `database/factories/AttendanceFactory.php` | Factory for `Attendance` |
| `app/Services/Governance/ConstituencyAnalytics.php` | All aggregation logic for Bishop/Governor endpoints |
| `app/Http/Controllers/Api/GovernorController.php` | 5 Governor endpoints |
| `app/Http/Controllers/Api/BishopController.php` | 7 Bishop endpoints |
| `tests/Unit/Services/Governance/ConstituencyAnalyticsTest.php` | DB-backed unit tests for the service |
| `tests/Feature/Api/GovernorControllerTest.php` | Feature tests for the 5 Governor endpoints |
| `tests/Feature/Api/BishopControllerTest.php` | Feature tests for the 7 Bishop endpoints |

**Edited files:**

| Path | Change |
|---|---|
| `phpunit.xml` | Enable SQLite in-memory for tests |
| `app/Http/Middleware/CheckRole.php` | Delegate to `Leader::hasAnyRole()` instead of legacy `user_type` |
| `database/seeders/DefaultGroupTypesSeeder.php` | Add `Constituency` GroupType row |
| `database/seeders/DefaultRolesSeeder.php` | Add `Bishop`, `Governor` role rows |
| `routes/tenant.php` | Register two new route groups |

---

## Task 1: Test infrastructure — SQLite + tenant migrations

**Goal:** A base `TestCase` that runs tenant migrations against an in-memory SQLite DB, so feature/unit tests can use `RefreshDatabase` semantics without needing real `stancl/tenancy` initialization.

**Files:**
- Modify: `phpunit.xml` (lines 22–23)
- Modify: `tests/TestCase.php`
- Test: `tests/Feature/Infrastructure/TestEnvironmentTest.php` (new sanity check)

- [ ] **Step 1: Enable SQLite in `phpunit.xml`**

Replace lines 22–23 (currently commented out) with the uncommented version:

```xml
        <env name="DB_CONNECTION" value="sqlite"/>
        <env name="DB_DATABASE" value=":memory:"/>
```

- [ ] **Step 2: Rewrite `tests/TestCase.php` to run tenant migrations**

```php
<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use RefreshDatabase;

    protected function refreshTestDatabase(): void
    {
        if (!RefreshDatabaseState::$migrated) {
            Artisan::call('migrate:fresh', [
                '--path' => 'database/migrations/tenant',
                '--realpath' => false,
            ]);
            RefreshDatabaseState::$migrated = true;
        }

        $this->beginDatabaseTransaction();
    }
}
```

Add an import: `use Illuminate\Foundation\Testing\RefreshDatabaseState;`

Note: this overrides the trait's `refreshTestDatabase` so we point at `database/migrations/tenant/` (where this project's tables live) instead of the default `database/migrations/`.

- [ ] **Step 3: Add a sanity test**

`tests/Feature/Infrastructure/TestEnvironmentTest.php`:

```php
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
```

- [ ] **Step 4: Run the sanity test**

Run: `php artisan test --filter TestEnvironmentTest`
Expected: PASS.

If it fails because a migration uses MySQL-only syntax, adjust the offending migration with a driver check (`Schema::getConnection()->getDriverName() === 'sqlite'`). Typical offenders are `enum`, `json` indexes, or `fullText`. **Do not** rewrite migrations broadly — only patch the minimum needed to load the schema in SQLite.

- [ ] **Step 5: Commit**

```bash
git add phpunit.xml tests/TestCase.php tests/Feature/Infrastructure/TestEnvironmentTest.php
git commit -m "test: SQLite-backed test infra running tenant migrations"
```

---

## Task 2: Factories — GroupType and Group

**Files:**
- Create: `database/factories/GroupTypeFactory.php`
- Create: `database/factories/GroupFactory.php`
- Test: re-run sanity test below

- [ ] **Step 1: Write `GroupTypeFactory`**

```php
<?php

namespace Database\Factories;

use App\Models\GroupType;
use Illuminate\Database\Eloquent\Factories\Factory;

class GroupTypeFactory extends Factory
{
    protected $model = GroupType::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(2, true),
            'slug' => $this->faker->unique()->slug(2),
            'level' => 0,
            'tracks_attendance' => false,
            'icon' => 'heroicon-o-user-group',
            'color' => '#6366f1',
        ];
    }

    public function constituency(): self
    {
        return $this->state(['name' => 'Constituency', 'slug' => 'constituency', 'level' => 0, 'tracks_attendance' => false]);
    }

    public function cellGroup(): self
    {
        return $this->state(['name' => 'Cell Group', 'slug' => 'cell-group', 'level' => 2, 'tracks_attendance' => true]);
    }
}
```

- [ ] **Step 2: Write `GroupFactory`**

```php
<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\GroupType;
use Illuminate\Database\Eloquent\Factories\Factory;

class GroupFactory extends Factory
{
    protected $model = Group::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(2, true),
            'group_type_id' => GroupType::factory(),
            'parent_id' => null,
            'leader_id' => null,
            'description' => null,
            'meeting_day' => 0,
            'meeting_time' => '10:00:00',
            'is_active' => true,
        ];
    }
}
```

- [ ] **Step 3: Add a factory smoke test**

Append to `tests/Feature/Infrastructure/TestEnvironmentTest.php`:

```php
public function test_group_factory_creates_record(): void
{
    $group = \App\Models\Group::factory()->create();
    $this->assertNotNull($group->id);
    $this->assertNotNull($group->group_type_id);
}
```

- [ ] **Step 4: Run the test**

Run: `php artisan test --filter TestEnvironmentTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add database/factories/GroupTypeFactory.php database/factories/GroupFactory.php tests/Feature/Infrastructure/TestEnvironmentTest.php
git commit -m "test: add GroupType and Group factories"
```

---

## Task 3: Factories — Member, Leader

**Files:**
- Create: `database/factories/MemberFactory.php`
- Create: `database/factories/LeaderFactory.php`

- [ ] **Step 1: Write `MemberFactory`**

```php
<?php

namespace Database\Factories;

use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

class MemberFactory extends Factory
{
    protected $model = Member::class;

    public function definition(): array
    {
        return [
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone_number' => $this->faker->phoneNumber(),
            'gender' => $this->faker->randomElement(['male', 'female']),
            'member_type' => 'member',
            'is_active' => true,
            'profile_completed' => true,
            'member_since' => now()->subYears(2),
        ];
    }
}
```

- [ ] **Step 2: Write `LeaderFactory`**

```php
<?php

namespace Database\Factories;

use App\Models\Leader;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeaderFactory extends Factory
{
    protected $model = Leader::class;

    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'username' => $this->faker->unique()->userName(),
            'password' => 'password',
            'is_active' => true,
        ];
    }
}
```

- [ ] **Step 3: Sanity-check**

Append to the existing infrastructure test:

```php
public function test_leader_factory_creates_with_member(): void
{
    $leader = \App\Models\Leader::factory()->create();
    $this->assertNotNull($leader->member);
    $this->assertNotNull($leader->member->first_name);
}
```

Run: `php artisan test --filter TestEnvironmentTest` → PASS.

- [ ] **Step 4: Commit**

```bash
git add database/factories/MemberFactory.php database/factories/LeaderFactory.php tests/Feature/Infrastructure/TestEnvironmentTest.php
git commit -m "test: add Member and Leader factories"
```

---

## Task 4: Factories — RoleDefinition, LeaderRole

**Files:**
- Create: `database/factories/RoleDefinitionFactory.php`
- Create: `database/factories/LeaderRoleFactory.php`

- [ ] **Step 1: Write `RoleDefinitionFactory`**

```php
<?php

namespace Database\Factories;

use App\Models\RoleDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

class RoleDefinitionFactory extends Factory
{
    protected $model = RoleDefinition::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->jobTitle(),
            'slug' => $this->faker->unique()->slug(2),
            'permission_level' => 50,
            'applies_to_group_type_id' => null,
            'is_active' => true,
        ];
    }

    public function bishop(): self
    {
        return $this->state(['name' => 'Bishop', 'slug' => 'bishop', 'permission_level' => 90, 'applies_to_group_type_id' => null]);
    }

    public function governor(): self
    {
        return $this->state(['name' => 'Governor', 'slug' => 'governor', 'permission_level' => 70]);
    }
}
```

- [ ] **Step 2: Write `LeaderRoleFactory`**

```php
<?php

namespace Database\Factories;

use App\Models\Leader;
use App\Models\LeaderRole;
use App\Models\RoleDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeaderRoleFactory extends Factory
{
    protected $model = LeaderRole::class;

    public function definition(): array
    {
        return [
            'leader_id' => Leader::factory(),
            'role_definition_id' => RoleDefinition::factory(),
            'group_id' => null,
            'assigned_at' => now(),
            'expires_at' => null,
            'is_active' => true,
        ];
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add database/factories/RoleDefinitionFactory.php database/factories/LeaderRoleFactory.php
git commit -m "test: add RoleDefinition and LeaderRole factories"
```

---

## Task 5: Factories — AttendanceSummary, Attendance + governance fixture trait

**Files:**
- Create: `database/factories/AttendanceSummaryFactory.php`
- Create: `database/factories/AttendanceFactory.php`
- Create: `tests/Concerns/BuildsGovernanceFixtures.php`

- [ ] **Step 1: Write `AttendanceSummaryFactory`**

```php
<?php

namespace Database\Factories;

use App\Models\AttendanceSummary;
use App\Models\Group;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttendanceSummaryFactory extends Factory
{
    protected $model = AttendanceSummary::class;

    public function definition(): array
    {
        return [
            'group_id' => Group::factory(),
            'date' => now()->startOfWeek()->next('Sunday')->toDateString(),
            'total_attendance' => $this->faker->numberBetween(20, 80),
            'visitor_count' => 0,
            'first_timer_count' => 0,
            'submitted_by_leader_id' => null,
            'notes' => null,
        ];
    }

    public function onSunday(\DateTimeInterface $week = null): self
    {
        $sunday = ($week ? \Carbon\Carbon::instance($week) : now())->startOfWeek()->next('Sunday');
        return $this->state(['date' => $sunday->toDateString()]);
    }

    public function onWednesday(\DateTimeInterface $week = null): self
    {
        $wednesday = ($week ? \Carbon\Carbon::instance($week) : now())->startOfWeek()->next('Wednesday');
        return $this->state(['date' => $wednesday->toDateString()]);
    }
}
```

- [ ] **Step 2: Write `AttendanceFactory`**

```php
<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\AttendanceSummary;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    public function definition(): array
    {
        return [
            'attendance_summary_id' => AttendanceSummary::factory(),
            'member_id' => Member::factory(),
            'attended' => true,
            'is_first_timer' => false,
            'is_visitor' => false,
            'is_new_convert' => false,
        ];
    }
}
```

- [ ] **Step 3: Write the fixture trait**

```php
<?php

namespace Tests\Concerns;

use App\Models\AttendanceSummary;
use App\Models\Group;
use App\Models\GroupType;
use App\Models\Leader;
use App\Models\LeaderRole;
use App\Models\Member;
use App\Models\RoleDefinition;
use Carbon\Carbon;

trait BuildsGovernanceFixtures
{
    protected GroupType $constituencyType;
    protected GroupType $cellGroupType;
    protected RoleDefinition $bishopRole;
    protected RoleDefinition $governorRole;

    protected function seedGovernanceTypes(): void
    {
        $this->constituencyType = GroupType::factory()->constituency()->create();
        $this->cellGroupType = GroupType::factory()->cellGroup()->create();
        $this->bishopRole = RoleDefinition::factory()->bishop()->create();
        $this->governorRole = RoleDefinition::factory()->governor()
            ->state(['applies_to_group_type_id' => $this->constituencyType->id])
            ->create();
    }

    protected function makeConstituency(string $name = 'North Constituency'): Group
    {
        return Group::factory()->create([
            'name' => $name,
            'group_type_id' => $this->constituencyType->id,
            'parent_id' => null,
        ]);
    }

    protected function makeCellGroup(Group $constituency, ?Leader $leader = null, string $name = null): Group
    {
        return Group::factory()->create([
            'name' => $name ?? 'Group ' . $this->faker->word,
            'group_type_id' => $this->cellGroupType->id,
            'parent_id' => $constituency->id,
            'leader_id' => $leader?->id,
        ]);
    }

    protected function makeMember(Group $cellGroup): Member
    {
        $member = Member::factory()->create();
        $member->groups()->attach($cellGroup->id, ['joined_at' => now(), 'is_primary' => true]);
        return $member;
    }

    protected function makeGovernor(Group $constituency): Leader
    {
        $leader = Leader::factory()->create();
        LeaderRole::factory()->create([
            'leader_id' => $leader->id,
            'role_definition_id' => $this->governorRole->id,
            'group_id' => $constituency->id,
            'is_active' => true,
        ]);
        return $leader;
    }

    protected function makeBishop(): Leader
    {
        $leader = Leader::factory()->create();
        LeaderRole::factory()->create([
            'leader_id' => $leader->id,
            'role_definition_id' => $this->bishopRole->id,
            'group_id' => null,
            'is_active' => true,
        ]);
        return $leader;
    }

    protected function submitAttendance(Group $cellGroup, Carbon $date, int $count = 30): AttendanceSummary
    {
        return AttendanceSummary::factory()->create([
            'group_id' => $cellGroup->id,
            'date' => $date->toDateString(),
            'total_attendance' => $count,
        ]);
    }
}
```

- [ ] **Step 4: Commit**

```bash
git add database/factories/AttendanceSummaryFactory.php database/factories/AttendanceFactory.php tests/Concerns/BuildsGovernanceFixtures.php
git commit -m "test: add Attendance factories and governance fixture trait"
```

---

## Task 6: Seed `Constituency` GroupType + `Bishop`/`Governor` roles

**Files:**
- Modify: `database/seeders/DefaultGroupTypesSeeder.php`
- Modify: `database/seeders/DefaultRolesSeeder.php`
- Test: `tests/Feature/Seeders/DefaultRolesSeederTest.php` (new)

- [ ] **Step 1: Add Constituency to `DefaultGroupTypesSeeder`**

In the `$types` array, append:

```php
['name' => 'Constituency', 'slug' => 'constituency', 'level' => 0, 'tracks_attendance' => false, 'icon' => 'heroicon-o-flag', 'color' => '#0ea5e9'],
```

- [ ] **Step 2: Add Bishop and Governor to `DefaultRolesSeeder`**

After the existing `$cellType` lookup, add:

```php
$constituencyType = GroupType::where('slug', 'constituency')->first();
```

In the `$roles` array, append (after Cell Leader):

```php
['name' => 'Bishop', 'slug' => 'bishop', 'permission_level' => 90, 'applies_to_group_type_id' => null],
['name' => 'Governor', 'slug' => 'governor', 'permission_level' => 70, 'applies_to_group_type_id' => $constituencyType?->id],
```

- [ ] **Step 3: Write a feature test for the seeders**

```php
<?php

namespace Tests\Feature\Seeders;

use App\Models\GroupType;
use App\Models\RoleDefinition;
use Database\Seeders\DefaultGroupTypesSeeder;
use Database\Seeders\DefaultRolesSeeder;
use Tests\TestCase;

class DefaultRolesSeederTest extends TestCase
{
    public function test_seeders_create_constituency_type_and_governance_roles(): void
    {
        $this->seed(DefaultGroupTypesSeeder::class);
        $this->seed(DefaultRolesSeeder::class);

        $constituency = GroupType::where('slug', 'constituency')->first();
        $this->assertNotNull($constituency);
        $this->assertSame(0, $constituency->level);
        $this->assertFalse((bool) $constituency->tracks_attendance);

        $bishop = RoleDefinition::where('slug', 'bishop')->first();
        $this->assertNotNull($bishop);
        $this->assertSame(90, $bishop->permission_level);
        $this->assertNull($bishop->applies_to_group_type_id);

        $governor = RoleDefinition::where('slug', 'governor')->first();
        $this->assertNotNull($governor);
        $this->assertSame(70, $governor->permission_level);
        $this->assertSame($constituency->id, $governor->applies_to_group_type_id);
    }

    public function test_existing_roles_remain_intact(): void
    {
        $this->seed(DefaultGroupTypesSeeder::class);
        $this->seed(DefaultRolesSeeder::class);

        foreach (['super-admin', 'zone-overseer', 'district-pastor', 'cell-leader'] as $slug) {
            $this->assertNotNull(RoleDefinition::where('slug', $slug)->first(), "missing $slug");
        }
    }
}
```

- [ ] **Step 4: Run the test**

Run: `php artisan test --filter DefaultRolesSeederTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add database/seeders/DefaultGroupTypesSeeder.php database/seeders/DefaultRolesSeeder.php tests/Feature/Seeders/DefaultRolesSeederTest.php
git commit -m "feat: seed Constituency type and Bishop/Governor roles"
```

---

## Task 7: Fix `CheckRole` middleware to use `LeaderRole`

**Files:**
- Modify: `app/Http/Middleware/CheckRole.php`
- Test: `tests/Feature/Middleware/CheckRoleTest.php` (new)

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\CheckRole;
use App\Models\Leader;
use App\Models\LeaderRole;
use App\Models\RoleDefinition;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\BuildsGovernanceFixtures;
use Tests\TestCase;

class CheckRoleTest extends TestCase
{
    use BuildsGovernanceFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedGovernanceTypes();

        Route::middleware(['auth:sanctum', CheckRole::class . ':bishop'])
            ->get('/_test/bishop-only', fn () => response()->json(['ok' => true]));

        Route::middleware(['auth:sanctum', CheckRole::class . ':bishop,governor'])
            ->get('/_test/bishop-or-governor', fn () => response()->json(['ok' => true]));
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/_test/bishop-only')->assertStatus(401);
    }

    public function test_leader_with_correct_role_passes(): void
    {
        $bishop = $this->makeBishop();
        $this->actingAs($bishop, 'sanctum')
            ->getJson('/_test/bishop-only')
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_leader_with_wrong_role_is_rejected(): void
    {
        $constituency = $this->makeConstituency();
        $governor = $this->makeGovernor($constituency);

        $this->actingAs($governor, 'sanctum')
            ->getJson('/_test/bishop-only')
            ->assertStatus(403)
            ->assertJson(['success' => false]);
    }

    public function test_either_role_passes_when_multiple_allowed(): void
    {
        $constituency = $this->makeConstituency();
        $governor = $this->makeGovernor($constituency);

        $this->actingAs($governor, 'sanctum')
            ->getJson('/_test/bishop-or-governor')
            ->assertOk();
    }
}
```

- [ ] **Step 2: Run it — expect FAIL**

Run: `php artisan test --filter CheckRoleTest`
Expected: FAIL — current middleware checks `user_type` which is not set on `Leader`.

- [ ] **Step 3: Rewrite `CheckRole`**

```php
<?php

namespace App\Http\Middleware;

use App\Models\Leader;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $leader = $request->user();

        if (!$leader instanceof Leader || !$leader->hasAnyRole($roles)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Required role: ' . implode(' or ', $roles),
            ], 403);
        }

        return $next($request);
    }
}
```

- [ ] **Step 4: Run the test — expect PASS**

Run: `php artisan test --filter CheckRoleTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Middleware/CheckRole.php tests/Feature/Middleware/CheckRoleTest.php
git commit -m "feat: CheckRole middleware uses LeaderRole instead of user_type"
```

---

## Task 8: `ConstituencyAnalytics::dashboard()`

**Files:**
- Create: `app/Services/Governance/ConstituencyAnalytics.php`
- Create: `tests/Unit/Services/Governance/ConstituencyAnalyticsTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services\Governance;

use App\Services\Governance\ConstituencyAnalytics;
use Carbon\Carbon;
use Tests\Concerns\BuildsGovernanceFixtures;
use Tests\TestCase;

class ConstituencyAnalyticsTest extends TestCase
{
    use BuildsGovernanceFixtures;

    private ConstituencyAnalytics $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedGovernanceTypes();
        $this->service = new ConstituencyAnalytics();
    }

    public function test_dashboard_aggregates_members_groups_leaders_and_attendance(): void
    {
        $constituency = $this->makeConstituency();
        $leader1 = \App\Models\Leader::factory()->create();
        $leader2 = \App\Models\Leader::factory()->create();

        $cellA = $this->makeCellGroup($constituency, $leader1);
        $cellB = $this->makeCellGroup($constituency, $leader2);

        for ($i = 0; $i < 5; $i++) $this->makeMember($cellA);
        for ($i = 0; $i < 7; $i++) $this->makeMember($cellB);

        $sunday = Carbon::now()->startOfWeek()->next('Sunday');
        $wednesday = Carbon::now()->startOfWeek()->next('Wednesday');

        $this->submitAttendance($cellA, $sunday, count: 4);
        $this->submitAttendance($cellB, $sunday, count: 6);
        $this->submitAttendance($cellA, $wednesday, count: 3);
        // cellB midweek: not submitted

        $result = $this->service->dashboard($constituency);

        $this->assertSame(12, $result['total_members']);
        $this->assertSame(2, $result['total_groups']);
        $this->assertSame(2, $result['total_leaders']);
        $this->assertSame(10, $result['sunday_attendance']);
        $this->assertSame(3, $result['midweek_attendance']);
        $this->assertSame(2, $result['groups_submitted_sunday']);
        $this->assertSame(1, $result['groups_submitted_midweek']);
    }
}
```

- [ ] **Step 2: Run the test — FAIL**

Run: `php artisan test --filter ConstituencyAnalyticsTest`
Expected: FAIL — class does not exist.

- [ ] **Step 3: Implement `dashboard()`**

```php
<?php

namespace App\Services\Governance;

use App\Models\AttendanceSummary;
use App\Models\Group;
use App\Models\Member;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Illuminate\Pagination\LengthAwarePaginator;

class ConstituencyAnalytics
{
    public function dashboard(Group $constituency): array
    {
        $cellGroupIds = $this->cellGroupIdsFor($constituency);

        $totalMembers = Member::whereHas('groups', fn ($q) =>
            $q->whereIn('groups.id', $cellGroupIds)
        )->count();

        // total_leaders = distinct leader_id assignments across the constituency's cell groups
        $totalLeaders = Group::whereIn('id', $cellGroupIds)
            ->whereNotNull('leader_id')
            ->distinct('leader_id')
            ->count('leader_id');

        [$weekStart, $weekEnd] = $this->currentWeekBounds();
        $thisWeekSummaries = AttendanceSummary::whereIn('group_id', $cellGroupIds)
            ->whereBetween('date', [$weekStart, $weekEnd])
            ->get();

        $sundayRows = $thisWeekSummaries->filter(fn ($s) => Carbon::parse($s->date)->isSunday());
        $midweekRows = $thisWeekSummaries->reject(fn ($s) => Carbon::parse($s->date)->isSunday());

        return [
            'total_members' => $totalMembers,
            'total_groups' => count($cellGroupIds),
            'total_leaders' => $totalLeaders,
            'sunday_attendance' => (int) $sundayRows->sum('total_attendance'),
            'midweek_attendance' => (int) $midweekRows->sum('total_attendance'),
            'groups_submitted_sunday' => $sundayRows->pluck('group_id')->unique()->count(),
            'groups_submitted_midweek' => $midweekRows->pluck('group_id')->unique()->count(),
        ];
    }

    protected function cellGroupIdsFor(Group $constituency): array
    {
        return Group::where('parent_id', $constituency->id)
            ->where('is_active', true)
            ->pluck('id')
            ->all();
    }

    protected function currentWeekBounds(): array
    {
        $start = Carbon::now()->startOfWeek();
        return [$start->toDateString(), $start->copy()->endOfWeek()->toDateString()];
    }
}
```

- [ ] **Step 4: Run the test — PASS**

Run: `php artisan test --filter ConstituencyAnalyticsTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Governance/ConstituencyAnalytics.php tests/Unit/Services/Governance/ConstituencyAnalyticsTest.php
git commit -m "feat: ConstituencyAnalytics::dashboard"
```

---

## Task 9: `ConstituencyAnalytics::groups()`

**Files:**
- Modify: `app/Services/Governance/ConstituencyAnalytics.php`
- Modify: `tests/Unit/Services/Governance/ConstituencyAnalyticsTest.php`

- [ ] **Step 1: Add the failing test**

```php
public function test_groups_returns_each_cell_group_with_submission_flags_and_latest_attendance(): void
{
    $constituency = $this->makeConstituency();
    $leader = \App\Models\Leader::factory()->create([
        'member_id' => \App\Models\Member::factory()->create(['first_name' => 'Kwame', 'last_name' => 'Asante'])->id,
    ]);

    $cell = $this->makeCellGroup($constituency, $leader, name: 'Grace Chapel');
    for ($i = 0; $i < 3; $i++) $this->makeMember($cell);

    $sunday = Carbon::now()->startOfWeek()->next('Sunday');
    $wednesday = Carbon::now()->startOfWeek()->next('Wednesday');
    $this->submitAttendance($cell, $sunday, count: 72);
    $this->submitAttendance($cell, $wednesday, count: 50);

    $groups = $this->service->groups($constituency);

    $this->assertCount(1, $groups);
    $this->assertSame('Grace Chapel', $groups[0]['name']);
    $this->assertSame(3, $groups[0]['members_count']);
    $this->assertSame('Kwame Asante', $groups[0]['leader_name']);
    $this->assertTrue($groups[0]['sunday_submitted']);
    $this->assertTrue($groups[0]['midweek_submitted']);
    $this->assertSame(72, $groups[0]['latest_sunday_attendance']);
    $this->assertSame(50, $groups[0]['latest_midweek_attendance']);
}

public function test_groups_returns_null_attendance_when_not_yet_submitted_this_week(): void
{
    $constituency = $this->makeConstituency();
    $cell = $this->makeCellGroup($constituency);

    $groups = $this->service->groups($constituency);

    $this->assertCount(1, $groups);
    $this->assertFalse($groups[0]['sunday_submitted']);
    $this->assertFalse($groups[0]['midweek_submitted']);
    $this->assertNull($groups[0]['latest_sunday_attendance']);
    $this->assertNull($groups[0]['latest_midweek_attendance']);
    $this->assertNull($groups[0]['leader_name']);
}
```

- [ ] **Step 2: Run — expect FAIL**

`php artisan test --filter ConstituencyAnalyticsTest`

- [ ] **Step 3: Implement `groups()`**

Add to `ConstituencyAnalytics`:

```php
public function groups(Group $constituency): array
{
    [$weekStart, $weekEnd] = $this->currentWeekBounds();

    $cellGroups = Group::where('parent_id', $constituency->id)
        ->where('is_active', true)
        ->with(['leader.member'])
        ->withCount('members')
        ->get();

    $cellGroupIds = $cellGroups->pluck('id')->all();
    $thisWeek = AttendanceSummary::whereIn('group_id', $cellGroupIds)
        ->whereBetween('date', [$weekStart, $weekEnd])
        ->get()
        ->groupBy('group_id');

    return $cellGroups->map(function ($g) use ($thisWeek) {
        $rows = $thisWeek->get($g->id, collect());
        $sundayRow = $rows->first(fn ($r) => Carbon::parse($r->date)->isSunday());
        $midweekRow = $rows->first(fn ($r) => !Carbon::parse($r->date)->isSunday());

        $leaderMember = $g->leader?->member;

        return [
            'id' => $g->id,
            'name' => $g->name,
            'members_count' => $g->members_count,
            'leader_name' => $leaderMember ? trim($leaderMember->first_name . ' ' . $leaderMember->last_name) : null,
            'sunday_submitted' => (bool) $sundayRow,
            'midweek_submitted' => (bool) $midweekRow,
            'latest_sunday_attendance' => $sundayRow?->total_attendance,
            'latest_midweek_attendance' => $midweekRow?->total_attendance,
        ];
    })->all();
}
```

- [ ] **Step 4: Run — PASS**

- [ ] **Step 5: Commit**

```bash
git add app/Services/Governance/ConstituencyAnalytics.php tests/Unit/Services/Governance/ConstituencyAnalyticsTest.php
git commit -m "feat: ConstituencyAnalytics::groups"
```

---

## Task 10: `ConstituencyAnalytics::groupDetail()`

**Files:**
- Modify: `app/Services/Governance/ConstituencyAnalytics.php`
- Modify: `tests/Unit/Services/Governance/ConstituencyAnalyticsTest.php`

- [ ] **Step 1: Add the failing test**

```php
public function test_group_detail_returns_group_fields_and_member_list(): void
{
    $constituency = $this->makeConstituency();
    $cell = $this->makeCellGroup($constituency, name: 'Grace Chapel');

    $alice = $this->makeMember($cell);
    $alice->update(['first_name' => 'Alice', 'last_name' => 'Mensah', 'is_active' => true]);
    $bob = $this->makeMember($cell);
    $bob->update(['first_name' => 'Bob', 'last_name' => 'Tetteh', 'is_active' => false]);

    $detail = $this->service->groupDetail($constituency, $cell->id);

    $this->assertSame('Grace Chapel', $detail['name']);
    $this->assertCount(2, $detail['members']);

    $aliceRow = collect($detail['members'])->firstWhere('first_name', 'Alice');
    $this->assertSame('Mensah', $aliceRow['last_name']);
    $this->assertTrue($aliceRow['is_active']);

    $bobRow = collect($detail['members'])->firstWhere('first_name', 'Bob');
    $this->assertFalse($bobRow['is_active']);
}

public function test_group_detail_returns_null_when_group_not_in_constituency(): void
{
    $constituencyA = $this->makeConstituency('A');
    $constituencyB = $this->makeConstituency('B');
    $cellInB = $this->makeCellGroup($constituencyB);

    $this->assertNull($this->service->groupDetail($constituencyA, $cellInB->id));
}
```

- [ ] **Step 2: Run — expect FAIL**

- [ ] **Step 3: Implement `groupDetail()`**

```php
public function groupDetail(Group $constituency, int $groupId): ?array
{
    $group = Group::where('id', $groupId)
        ->where('parent_id', $constituency->id)
        ->with(['leader.member', 'members'])
        ->withCount('members')
        ->first();

    if (!$group) {
        return null;
    }

    [$weekStart, $weekEnd] = $this->currentWeekBounds();
    $rows = AttendanceSummary::where('group_id', $group->id)
        ->whereBetween('date', [$weekStart, $weekEnd])
        ->get();

    $sundayRow = $rows->first(fn ($r) => Carbon::parse($r->date)->isSunday());
    $midweekRow = $rows->first(fn ($r) => !Carbon::parse($r->date)->isSunday());
    $leaderMember = $group->leader?->member;

    return [
        'id' => $group->id,
        'name' => $group->name,
        'members_count' => $group->members_count,
        'leader_name' => $leaderMember ? trim($leaderMember->first_name . ' ' . $leaderMember->last_name) : null,
        'sunday_submitted' => (bool) $sundayRow,
        'midweek_submitted' => (bool) $midweekRow,
        'latest_sunday_attendance' => $sundayRow?->total_attendance,
        'latest_midweek_attendance' => $midweekRow?->total_attendance,
        'members' => $group->members->map(fn ($m) => [
            'id' => $m->id,
            'first_name' => $m->first_name,
            'last_name' => $m->last_name,
            'is_active' => (bool) $m->is_active,
        ])->values()->all(),
    ];
}
```

- [ ] **Step 4: Run — PASS**

- [ ] **Step 5: Commit**

```bash
git commit -am "feat: ConstituencyAnalytics::groupDetail"
```

---

## Task 11: `ConstituencyAnalytics::members()` — paginated

**Files:**
- Modify: `app/Services/Governance/ConstituencyAnalytics.php`
- Modify: `tests/Unit/Services/Governance/ConstituencyAnalyticsTest.php`

- [ ] **Step 1: Add the failing test**

```php
public function test_members_returns_paginated_unique_members_across_constituency(): void
{
    $constituency = $this->makeConstituency();
    $cellA = $this->makeCellGroup($constituency);
    $cellB = $this->makeCellGroup($constituency);

    for ($i = 0; $i < 30; $i++) $this->makeMember($cellA);
    for ($i = 0; $i < 20; $i++) $this->makeMember($cellB);

    $page1 = $this->service->members($constituency, perPage: 25);

    $this->assertSame(50, $page1->total());
    $this->assertSame(25, $page1->perPage());
    $this->assertCount(25, $page1->items());
    $this->assertSame(1, $page1->currentPage());
}

public function test_members_excludes_members_in_groups_outside_constituency(): void
{
    $constituencyA = $this->makeConstituency('A');
    $constituencyB = $this->makeConstituency('B');
    $cellA = $this->makeCellGroup($constituencyA);
    $cellB = $this->makeCellGroup($constituencyB);

    $this->makeMember($cellA);
    $this->makeMember($cellB);

    $page = $this->service->members($constituencyA);

    $this->assertSame(1, $page->total());
}
```

- [ ] **Step 2: Run — FAIL**

- [ ] **Step 3: Implement `members()`**

```php
public function members(Group $constituency, int $perPage = 25): LengthAwarePaginator
{
    $cellGroupIds = $this->cellGroupIdsFor($constituency);

    return Member::whereHas('groups', fn ($q) => $q->whereIn('groups.id', $cellGroupIds))
        ->orderBy('last_name')
        ->orderBy('first_name')
        ->paginate($perPage);
}
```

- [ ] **Step 4: Run — PASS**

- [ ] **Step 5: Commit**

```bash
git commit -am "feat: ConstituencyAnalytics::members paginated"
```

---

## Task 12: `ConstituencyAnalytics::attendance()` — date-ranged series

**Files:**
- Modify: `app/Services/Governance/ConstituencyAnalytics.php`
- Modify: `tests/Unit/Services/Governance/ConstituencyAnalyticsTest.php`

The shape returned: `{ series: [{date, sunday|null, midweek|null}], totals: {sunday, midweek} }`. `series` keyed by ISO date with one row per *date that had at least one submission* in the range, summing across groups. `totals` are full-range sums.

- [ ] **Step 1: Add the failing test**

```php
public function test_attendance_returns_series_summed_across_groups_split_by_day_of_week(): void
{
    $constituency = $this->makeConstituency();
    $cellA = $this->makeCellGroup($constituency);
    $cellB = $this->makeCellGroup($constituency);

    $sunday1 = Carbon::create(2026, 4, 5);   // Sunday
    $wed1    = Carbon::create(2026, 4, 8);   // Wednesday
    $sunday2 = Carbon::create(2026, 4, 12);  // Sunday

    $this->submitAttendance($cellA, $sunday1, count: 50);
    $this->submitAttendance($cellB, $sunday1, count: 30);
    $this->submitAttendance($cellA, $wed1, count: 20);
    $this->submitAttendance($cellA, $sunday2, count: 60);

    $range = CarbonInterval::create()
        ->setStart(Carbon::create(2026, 4, 1))
        ->setEnd(Carbon::create(2026, 4, 30));

    $result = $this->service->attendance($constituency, $range);

    $this->assertSame(['sunday' => 140, 'midweek' => 20], $result['totals']);

    $byDate = collect($result['series'])->keyBy('date');
    $this->assertSame(80, $byDate['2026-04-05']['sunday']);
    $this->assertNull($byDate['2026-04-05']['midweek']);
    $this->assertNull($byDate['2026-04-08']['sunday']);
    $this->assertSame(20, $byDate['2026-04-08']['midweek']);
    $this->assertSame(60, $byDate['2026-04-12']['sunday']);
}
```

`CarbonInterval` import: `use Carbon\CarbonInterval;`

- [ ] **Step 2: Run — FAIL**

- [ ] **Step 3: Implement `attendance()`**

```php
public function attendance(Group $constituency, CarbonInterval $range): array
{
    $cellGroupIds = $this->cellGroupIdsFor($constituency);
    $start = Carbon::parse($range->getStartDate())->toDateString();
    $end = Carbon::parse($range->getEndDate())->toDateString();

    $rows = AttendanceSummary::whereIn('group_id', $cellGroupIds)
        ->whereBetween('date', [$start, $end])
        ->orderBy('date')
        ->get();

    $byDate = $rows->groupBy(fn ($r) => Carbon::parse($r->date)->toDateString());

    $series = $byDate->map(function ($dayRows, $date) {
        $isSunday = Carbon::parse($date)->isSunday();
        $sum = (int) $dayRows->sum('total_attendance');
        return [
            'date' => $date,
            'sunday' => $isSunday ? $sum : null,
            'midweek' => $isSunday ? null : $sum,
        ];
    })->values()->all();

    $totalSunday = (int) $rows->filter(fn ($r) => Carbon::parse($r->date)->isSunday())->sum('total_attendance');
    $totalMidweek = (int) $rows->reject(fn ($r) => Carbon::parse($r->date)->isSunday())->sum('total_attendance');

    return [
        'series' => $series,
        'totals' => ['sunday' => $totalSunday, 'midweek' => $totalMidweek],
    ];
}
```

- [ ] **Step 4: Run — PASS**

- [ ] **Step 5: Commit**

```bash
git commit -am "feat: ConstituencyAnalytics::attendance"
```

---

## Task 13: Tenant-wide analytics methods + constituency summaries

**Files:**
- Modify: `app/Services/Governance/ConstituencyAnalytics.php`
- Modify: `tests/Unit/Services/Governance/ConstituencyAnalyticsTest.php`

- [ ] **Step 1: Add failing tests**

```php
public function test_tenant_wide_attendance_aggregates_across_all_constituencies(): void
{
    $constA = $this->makeConstituency('A');
    $constB = $this->makeConstituency('B');
    $cellA = $this->makeCellGroup($constA);
    $cellB = $this->makeCellGroup($constB);

    $sunday = Carbon::create(2026, 4, 5);
    $this->submitAttendance($cellA, $sunday, count: 30);
    $this->submitAttendance($cellB, $sunday, count: 40);

    $range = CarbonInterval::create()
        ->setStart(Carbon::create(2026, 4, 1))
        ->setEnd(Carbon::create(2026, 4, 30));

    $result = $this->service->tenantWideAttendance($range);

    $this->assertSame(70, $result['totals']['sunday']);
    $this->assertSame(0, $result['totals']['midweek']);
}

public function test_tenant_wide_members_paginates_across_all_constituencies(): void
{
    $constA = $this->makeConstituency('A');
    $constB = $this->makeConstituency('B');
    $cellA = $this->makeCellGroup($constA);
    $cellB = $this->makeCellGroup($constB);

    for ($i = 0; $i < 10; $i++) $this->makeMember($cellA);
    for ($i = 0; $i < 10; $i++) $this->makeMember($cellB);

    $page = $this->service->tenantWideMembers(perPage: 25);

    $this->assertSame(20, $page->total());
}

public function test_tenant_wide_members_excludes_orphan_members(): void
{
    $constA = $this->makeConstituency('A');
    $cellA = $this->makeCellGroup($constA);
    $this->makeMember($cellA);

    // Member in a group not parented to a Constituency
    $orphanCell = \App\Models\Group::factory()->create([
        'group_type_id' => $this->cellGroupType->id,
        'parent_id' => null,
    ]);
    $this->makeMember($orphanCell);

    $page = $this->service->tenantWideMembers();

    $this->assertSame(1, $page->total());
}

public function test_constituency_summaries_returns_one_row_per_constituency(): void
{
    $constA = $this->makeConstituency('North');
    $constB = $this->makeConstituency('South');

    $cellA1 = $this->makeCellGroup($constA);
    $cellA2 = $this->makeCellGroup($constA);
    $cellB1 = $this->makeCellGroup($constB);

    for ($i = 0; $i < 5; $i++) $this->makeMember($cellA1);
    for ($i = 0; $i < 3; $i++) $this->makeMember($cellA2);
    for ($i = 0; $i < 7; $i++) $this->makeMember($cellB1);

    $governorA = $this->makeGovernor($constA);
    $governorA->member->update(['first_name' => 'Samuel', 'last_name' => 'Kofi']);
    // constB has no governor assigned

    $sunday = Carbon::now()->startOfWeek()->next('Sunday');
    $this->submitAttendance($cellA1, $sunday, count: 4);
    $this->submitAttendance($cellB1, $sunday, count: 6);

    $summaries = collect($this->service->constituencySummaries())->keyBy('constituency_name');

    $this->assertSame(8, $summaries['North']['total_members']);
    $this->assertSame(2, $summaries['North']['total_groups']);
    $this->assertSame(4, $summaries['North']['sunday_attendance']);
    $this->assertSame('Samuel', $summaries['North']['governor']['member']['first_name']);

    $this->assertSame(7, $summaries['South']['total_members']);
    $this->assertNull($summaries['South']['governor']);
}
```

- [ ] **Step 2: Run — FAIL**

- [ ] **Step 3: Implement the three methods**

Add to `ConstituencyAnalytics`:

```php
public function tenantWideAttendance(CarbonInterval $range): array
{
    $cellGroupIds = $this->allConstituencyCellGroupIds();
    return $this->attendanceForCellGroups($cellGroupIds, $range);
}

public function tenantWideMembers(int $perPage = 25): LengthAwarePaginator
{
    $cellGroupIds = $this->allConstituencyCellGroupIds();

    return Member::whereHas('groups', fn ($q) => $q->whereIn('groups.id', $cellGroupIds))
        ->orderBy('last_name')->orderBy('first_name')
        ->paginate($perPage);
}

public function constituencySummaries(): array
{
    [$weekStart, $weekEnd] = $this->currentWeekBounds();
    $constituencyTypeId = \App\Models\GroupType::where('slug', 'constituency')->value('id');
    if (!$constituencyTypeId) return [];

    $constituencies = Group::where('group_type_id', $constituencyTypeId)
        ->where('is_active', true)
        ->get();

    return $constituencies->map(function (Group $c) use ($weekStart, $weekEnd) {
        $cellGroupIds = $this->cellGroupIdsFor($c);

        $totalMembers = Member::whereHas('groups', fn ($q) => $q->whereIn('groups.id', $cellGroupIds))->count();

        $rows = AttendanceSummary::whereIn('group_id', $cellGroupIds)
            ->whereBetween('date', [$weekStart, $weekEnd])
            ->get();

        $sunday = (int) $rows->filter(fn ($r) => Carbon::parse($r->date)->isSunday())->sum('total_attendance');
        $midweek = (int) $rows->reject(fn ($r) => Carbon::parse($r->date)->isSunday())->sum('total_attendance');

        $governorRole = \App\Models\LeaderRole::where('group_id', $c->id)
            ->where('is_active', true)
            ->whereHas('roleDefinition', fn ($q) => $q->where('slug', 'governor'))
            ->with('leader.member')
            ->first();

        $governor = null;
        if ($governorRole && $governorRole->leader) {
            $governor = [
                'id' => $governorRole->leader->id,
                'member' => [
                    'id' => $governorRole->leader->member->id,
                    'first_name' => $governorRole->leader->member->first_name,
                    'last_name' => $governorRole->leader->member->last_name,
                ],
            ];
        }

        return [
            'id' => $c->id,
            'constituency_name' => $c->name,
            'total_members' => $totalMembers,
            'total_groups' => count($cellGroupIds),
            'sunday_attendance' => $sunday,
            'midweek_attendance' => $midweek,
            'governor' => $governor,
        ];
    })->all();
}

protected function allConstituencyCellGroupIds(): array
{
    $constituencyTypeId = \App\Models\GroupType::where('slug', 'constituency')->value('id');
    if (!$constituencyTypeId) return [];

    return Group::whereIn('parent_id', function ($q) use ($constituencyTypeId) {
        $q->select('id')->from('groups')->where('group_type_id', $constituencyTypeId);
    })
    ->where('is_active', true)
    ->pluck('id')
    ->all();
}

protected function attendanceForCellGroups(array $cellGroupIds, CarbonInterval $range): array
{
    $start = Carbon::parse($range->getStartDate())->toDateString();
    $end = Carbon::parse($range->getEndDate())->toDateString();

    $rows = AttendanceSummary::whereIn('group_id', $cellGroupIds)
        ->whereBetween('date', [$start, $end])
        ->orderBy('date')
        ->get();

    $byDate = $rows->groupBy(fn ($r) => Carbon::parse($r->date)->toDateString());

    $series = $byDate->map(function ($dayRows, $date) {
        $isSunday = Carbon::parse($date)->isSunday();
        $sum = (int) $dayRows->sum('total_attendance');
        return ['date' => $date, 'sunday' => $isSunday ? $sum : null, 'midweek' => $isSunday ? null : $sum];
    })->values()->all();

    $totalSunday = (int) $rows->filter(fn ($r) => Carbon::parse($r->date)->isSunday())->sum('total_attendance');
    $totalMidweek = (int) $rows->reject(fn ($r) => Carbon::parse($r->date)->isSunday())->sum('total_attendance');

    return ['series' => $series, 'totals' => ['sunday' => $totalSunday, 'midweek' => $totalMidweek]];
}
```

Refactor `attendance()` to call `attendanceForCellGroups()`:

```php
public function attendance(Group $constituency, CarbonInterval $range): array
{
    return $this->attendanceForCellGroups($this->cellGroupIdsFor($constituency), $range);
}
```

- [ ] **Step 4: Run all analytics tests — PASS**

Run: `php artisan test --filter ConstituencyAnalyticsTest`

- [ ] **Step 5: Commit**

```bash
git commit -am "feat: ConstituencyAnalytics tenant-wide methods + constituency summaries"
```

---

## Task 14: `GovernorController` + 5 endpoints + routes

**Files:**
- Create: `app/Http/Controllers/Api/GovernorController.php`
- Create: `tests/Feature/Api/GovernorControllerTest.php`
- Modify: `routes/tenant.php`

- [ ] **Step 1: Write the failing feature test**

```php
<?php

namespace Tests\Feature\Api;

use App\Models\Member;
use Carbon\Carbon;
use Tests\Concerns\BuildsGovernanceFixtures;
use Tests\TestCase;

class GovernorControllerTest extends TestCase
{
    use BuildsGovernanceFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedGovernanceTypes();
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/governor/dashboard')->assertStatus(401);
    }

    public function test_wrong_role_is_rejected(): void
    {
        $bishop = $this->makeBishop();
        $this->actingAs($bishop, 'sanctum')
            ->getJson('/api/v1/governor/dashboard')
            ->assertStatus(403);
    }

    public function test_dashboard_returns_documented_shape(): void
    {
        $constituency = $this->makeConstituency();
        $governor = $this->makeGovernor($constituency);
        $cell = $this->makeCellGroup($constituency);
        $this->makeMember($cell);

        $this->actingAs($governor, 'sanctum')
            ->getJson('/api/v1/governor/dashboard')
            ->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure(['success', 'data' => [
                'total_members', 'total_groups', 'total_leaders',
                'sunday_attendance', 'midweek_attendance',
                'groups_submitted_sunday', 'groups_submitted_midweek',
            ]]);
    }

    public function test_groups_returns_array(): void
    {
        $constituency = $this->makeConstituency();
        $governor = $this->makeGovernor($constituency);
        $this->makeCellGroup($constituency);

        $this->actingAs($governor, 'sanctum')
            ->getJson('/api/v1/governor/groups')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_group_detail_404s_for_group_outside_constituency(): void
    {
        $myConstituency = $this->makeConstituency('Mine');
        $otherConstituency = $this->makeConstituency('Other');
        $governor = $this->makeGovernor($myConstituency);
        $foreignCell = $this->makeCellGroup($otherConstituency);

        $this->actingAs($governor, 'sanctum')
            ->getJson("/api/v1/governor/groups/{$foreignCell->id}")
            ->assertStatus(404);
    }

    public function test_group_detail_returns_members(): void
    {
        $constituency = $this->makeConstituency();
        $governor = $this->makeGovernor($constituency);
        $cell = $this->makeCellGroup($constituency);
        $this->makeMember($cell);

        $this->actingAs($governor, 'sanctum')
            ->getJson("/api/v1/governor/groups/{$cell->id}")
            ->assertOk()
            ->assertJsonStructure(['data' => ['id', 'name', 'members' => [['id', 'first_name', 'last_name', 'is_active']]]]);
    }

    public function test_members_paginates(): void
    {
        $constituency = $this->makeConstituency();
        $governor = $this->makeGovernor($constituency);
        $cell = $this->makeCellGroup($constituency);
        for ($i = 0; $i < 30; $i++) $this->makeMember($cell);

        $r = $this->actingAs($governor, 'sanctum')
            ->getJson('/api/v1/governor/members?per_page=10')
            ->assertOk();

        $this->assertSame(10, count($r->json('data.data')));
        $this->assertSame(30, $r->json('data.total'));
    }

    public function test_attendance_returns_series_and_totals(): void
    {
        $constituency = $this->makeConstituency();
        $governor = $this->makeGovernor($constituency);
        $cell = $this->makeCellGroup($constituency);

        $sunday = Carbon::create(2026, 4, 5);
        $this->submitAttendance($cell, $sunday, 50);

        $this->actingAs($governor, 'sanctum')
            ->getJson('/api/v1/governor/attendance?from=2026-04-01&to=2026-04-30')
            ->assertOk()
            ->assertJsonStructure(['data' => ['series', 'totals' => ['sunday', 'midweek']]]);
    }

    public function test_misconfigured_governor_with_null_group_id_returns_403(): void
    {
        $leader = \App\Models\Leader::factory()->create();
        \App\Models\LeaderRole::factory()->create([
            'leader_id' => $leader->id,
            'role_definition_id' => $this->governorRole->id,
            'group_id' => null,
            'is_active' => true,
        ]);

        $this->actingAs($leader, 'sanctum')
            ->getJson('/api/v1/governor/dashboard')
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }
}
```

- [ ] **Step 2: Run — FAIL**

`php artisan test --filter GovernorControllerTest` — fails because routes and controller don't exist.

- [ ] **Step 3: Implement the controller**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Services\Governance\ConstituencyAnalytics;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GovernorController extends Controller
{
    public function __construct(private readonly ConstituencyAnalytics $service)
    {
    }

    public function dashboard(Request $request): JsonResponse
    {
        return $this->ok($this->service->dashboard($this->constituency($request)));
    }

    public function groups(Request $request): JsonResponse
    {
        return $this->ok($this->service->groups($this->constituency($request)));
    }

    public function groupDetail(Request $request, int $id): JsonResponse
    {
        $detail = $this->service->groupDetail($this->constituency($request), $id);
        if (!$detail) {
            return response()->json(['success' => false, 'message' => 'group not found'], 404);
        }
        return $this->ok($detail);
    }

    public function members(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 25);
        return $this->ok($this->service->members($this->constituency($request), $perPage));
    }

    public function attendance(Request $request): JsonResponse
    {
        return $this->ok($this->service->attendance(
            $this->constituency($request),
            $this->dateRange($request),
        ));
    }

    protected function constituency(Request $request): Group
    {
        $role = $request->user()->leaderRoles()
            ->where('is_active', true)
            ->whereNotNull('group_id')
            ->whereHas('roleDefinition', fn ($q) => $q->where('slug', 'governor'))
            ->with('group')
            ->first();

        if (!$role || !$role->group) {
            abort(response()->json(['success' => false, 'message' => 'no constituency assigned'], 403));
        }
        return $role->group;
    }

    protected function dateRange(Request $request): CarbonInterval
    {
        $from = $request->query('from') ? Carbon::parse($request->query('from')) : Carbon::now()->startOfWeek();
        $to = $request->query('to') ? Carbon::parse($request->query('to')) : Carbon::now()->endOfWeek();
        return CarbonInterval::create()->setStart($from)->setEnd($to);
    }

    protected function ok(mixed $data): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data]);
    }
}
```

- [ ] **Step 4: Register routes**

In `routes/tenant.php`, inside the `auth:sanctum` + `InitializeLeaderScope` group, append:

```php
Route::prefix('governor')->middleware([\App\Http\Middleware\CheckRole::class . ':governor'])->group(function () {
    Route::get('dashboard',     [App\Http\Controllers\Api\GovernorController::class, 'dashboard']);
    Route::get('groups',        [App\Http\Controllers\Api\GovernorController::class, 'groups']);
    Route::get('groups/{id}',   [App\Http\Controllers\Api\GovernorController::class, 'groupDetail'])->whereNumber('id');
    Route::get('members',       [App\Http\Controllers\Api\GovernorController::class, 'members']);
    Route::get('attendance',    [App\Http\Controllers\Api\GovernorController::class, 'attendance']);
});
```

- [ ] **Step 5: Run — PASS**

`php artisan test --filter GovernorControllerTest`

If the misconfigured-governor test fails because `CheckRole` rejects before `constituency()` runs, the fix is order: `CheckRole` only checks role-slug presence, not `group_id`. The leader has the slug active but `group_id` is null. `CheckRole` passes; the controller's `constituency()` then 403s. This is the intended path.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/GovernorController.php tests/Feature/Api/GovernorControllerTest.php routes/tenant.php
git commit -m "feat: GovernorController with 5 endpoints"
```

---

## Task 15: `BishopController` + 7 endpoints + routes

**Files:**
- Create: `app/Http/Controllers/Api/BishopController.php`
- Create: `tests/Feature/Api/BishopControllerTest.php`
- Modify: `routes/tenant.php`

- [ ] **Step 1: Write the failing feature test**

```php
<?php

namespace Tests\Feature\Api;

use Carbon\Carbon;
use Tests\Concerns\BuildsGovernanceFixtures;
use Tests\TestCase;

class BishopControllerTest extends TestCase
{
    use BuildsGovernanceFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedGovernanceTypes();
    }

    public function test_unauthenticated_is_rejected(): void
    {
        $this->getJson('/api/v1/bishop/governors')->assertStatus(401);
    }

    public function test_wrong_role_is_rejected(): void
    {
        $constituency = $this->makeConstituency();
        $governor = $this->makeGovernor($constituency);

        $this->actingAs($governor, 'sanctum')
            ->getJson('/api/v1/bishop/governors')
            ->assertStatus(403);
    }

    public function test_governors_returns_summary_per_constituency(): void
    {
        $bishop = $this->makeBishop();
        $constA = $this->makeConstituency('North');
        $constB = $this->makeConstituency('South');
        $this->makeGovernor($constA);

        $r = $this->actingAs($bishop, 'sanctum')
            ->getJson('/api/v1/bishop/governors')
            ->assertOk();

        $this->assertSame(true, $r->json('success'));
        $this->assertCount(2, $r->json('data'));
    }

    public function test_governors_returns_empty_array_for_empty_tenant(): void
    {
        $bishop = $this->makeBishop();

        $this->actingAs($bishop, 'sanctum')
            ->getJson('/api/v1/bishop/governors')
            ->assertOk()
            ->assertJson(['success' => true, 'data' => []]);
    }

    public function test_tenant_wide_attendance_endpoint(): void
    {
        $bishop = $this->makeBishop();
        $const = $this->makeConstituency();
        $cell = $this->makeCellGroup($const);
        $this->submitAttendance($cell, Carbon::create(2026, 4, 5), 50);

        $r = $this->actingAs($bishop, 'sanctum')
            ->getJson('/api/v1/bishop/attendance?from=2026-04-01&to=2026-04-30')
            ->assertOk();

        $this->assertSame(50, $r->json('data.totals.sunday'));
    }

    public function test_tenant_wide_members_paginates(): void
    {
        $bishop = $this->makeBishop();
        $const = $this->makeConstituency();
        $cell = $this->makeCellGroup($const);
        for ($i = 0; $i < 5; $i++) $this->makeMember($cell);

        $this->actingAs($bishop, 'sanctum')
            ->getJson('/api/v1/bishop/members?per_page=2')
            ->assertOk()
            ->assertJsonPath('data.total', 5)
            ->assertJsonPath('data.per_page', 2);
    }

    public function test_governor_dashboard_drilldown(): void
    {
        $bishop = $this->makeBishop();
        $const = $this->makeConstituency();
        $governor = $this->makeGovernor($const);

        $this->actingAs($bishop, 'sanctum')
            ->getJson("/api/v1/bishop/governors/{$governor->id}/dashboard")
            ->assertOk()
            ->assertJsonStructure(['data' => ['total_members', 'total_groups']]);
    }

    public function test_governor_drilldown_404s_for_non_governor_id(): void
    {
        $bishop = $this->makeBishop();
        $randomLeader = \App\Models\Leader::factory()->create();

        $this->actingAs($bishop, 'sanctum')
            ->getJson("/api/v1/bishop/governors/{$randomLeader->id}/dashboard")
            ->assertStatus(404);
    }

    public function test_governor_drilldown_groups_endpoint(): void
    {
        $bishop = $this->makeBishop();
        $const = $this->makeConstituency();
        $governor = $this->makeGovernor($const);
        $this->makeCellGroup($const);

        $this->actingAs($bishop, 'sanctum')
            ->getJson("/api/v1/bishop/governors/{$governor->id}/groups")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_governor_drilldown_attendance_endpoint(): void
    {
        $bishop = $this->makeBishop();
        $const = $this->makeConstituency();
        $governor = $this->makeGovernor($const);

        $this->actingAs($bishop, 'sanctum')
            ->getJson("/api/v1/bishop/governors/{$governor->id}/attendance?from=2026-04-01&to=2026-04-30")
            ->assertOk()
            ->assertJsonStructure(['data' => ['series', 'totals']]);
    }

    public function test_group_detail_drilldown(): void
    {
        $bishop = $this->makeBishop();
        $const = $this->makeConstituency();
        $governor = $this->makeGovernor($const);
        $cell = $this->makeCellGroup($const);
        $this->makeMember($cell);

        $this->actingAs($bishop, 'sanctum')
            ->getJson("/api/v1/bishop/governors/{$governor->id}/groups/{$cell->id}")
            ->assertOk()
            ->assertJsonStructure(['data' => ['id', 'name', 'members']]);
    }

    public function test_group_detail_drilldown_404s_for_group_outside_governor_constituency(): void
    {
        $bishop = $this->makeBishop();
        $constA = $this->makeConstituency('A');
        $constB = $this->makeConstituency('B');
        $governorA = $this->makeGovernor($constA);
        $cellInB = $this->makeCellGroup($constB);

        $this->actingAs($bishop, 'sanctum')
            ->getJson("/api/v1/bishop/governors/{$governorA->id}/groups/{$cellInB->id}")
            ->assertStatus(404);
    }
}
```

- [ ] **Step 2: Run — FAIL**

- [ ] **Step 3: Implement the controller**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Leader;
use App\Models\LeaderRole;
use App\Services\Governance\ConstituencyAnalytics;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BishopController extends Controller
{
    public function __construct(private readonly ConstituencyAnalytics $service)
    {
    }

    public function governors(): JsonResponse
    {
        return $this->ok($this->service->constituencySummaries());
    }

    public function attendance(Request $request): JsonResponse
    {
        return $this->ok($this->service->tenantWideAttendance($this->dateRange($request)));
    }

    public function members(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 25);
        return $this->ok($this->service->tenantWideMembers($perPage));
    }

    public function governorDashboard(int $govId): JsonResponse
    {
        return $this->ok($this->service->dashboard($this->constituencyForGovernor($govId)));
    }

    public function governorGroups(int $govId): JsonResponse
    {
        return $this->ok($this->service->groups($this->constituencyForGovernor($govId)));
    }

    public function governorAttendance(Request $request, int $govId): JsonResponse
    {
        return $this->ok($this->service->attendance(
            $this->constituencyForGovernor($govId),
            $this->dateRange($request),
        ));
    }

    public function groupDetail(int $govId, int $groupId): JsonResponse
    {
        $detail = $this->service->groupDetail($this->constituencyForGovernor($govId), $groupId);
        if (!$detail) {
            return response()->json(['success' => false, 'message' => 'group not found'], 404);
        }
        return $this->ok($detail);
    }

    protected function constituencyForGovernor(int $govId): Group
    {
        $role = LeaderRole::where('leader_id', $govId)
            ->where('is_active', true)
            ->whereNotNull('group_id')
            ->whereHas('roleDefinition', fn ($q) => $q->where('slug', 'governor'))
            ->with('group')
            ->first();

        if (!$role || !$role->group) {
            abort(response()->json(['success' => false, 'message' => 'governor not found'], 404));
        }
        return $role->group;
    }

    protected function dateRange(Request $request): CarbonInterval
    {
        $from = $request->query('from') ? Carbon::parse($request->query('from')) : Carbon::now()->startOfWeek();
        $to = $request->query('to') ? Carbon::parse($request->query('to')) : Carbon::now()->endOfWeek();
        return CarbonInterval::create()->setStart($from)->setEnd($to);
    }

    protected function ok(mixed $data): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data]);
    }
}
```

- [ ] **Step 4: Register routes**

In `routes/tenant.php`, inside the `auth:sanctum` + `InitializeLeaderScope` group, after the Governor block, append:

```php
Route::prefix('bishop')->middleware([\App\Http\Middleware\CheckRole::class . ':bishop'])->group(function () {
    Route::get('governors',  [App\Http\Controllers\Api\BishopController::class, 'governors']);
    Route::get('attendance', [App\Http\Controllers\Api\BishopController::class, 'attendance']);
    Route::get('members',    [App\Http\Controllers\Api\BishopController::class, 'members']);
    Route::get('governors/{govId}/dashboard',          [App\Http\Controllers\Api\BishopController::class, 'governorDashboard'])->whereNumber('govId');
    Route::get('governors/{govId}/groups',             [App\Http\Controllers\Api\BishopController::class, 'governorGroups'])->whereNumber('govId');
    Route::get('governors/{govId}/groups/{groupId}',   [App\Http\Controllers\Api\BishopController::class, 'groupDetail'])->whereNumber('govId')->whereNumber('groupId');
    Route::get('governors/{govId}/attendance',         [App\Http\Controllers\Api\BishopController::class, 'governorAttendance'])->whereNumber('govId');
});
```

- [ ] **Step 5: Run — PASS**

`php artisan test --filter BishopControllerTest`

- [ ] **Step 6: Run the full test suite**

Run: `php artisan test`
Expected: every test in the suite passes (including the existing `NormalizeNameTest`).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Api/BishopController.php tests/Feature/Api/BishopControllerTest.php routes/tenant.php
git commit -m "feat: BishopController with 7 endpoints"
```

---

## Verification

After all 15 tasks complete:

- `php artisan test` — all green.
- `php artisan route:list --path=governor` lists 5 routes.
- `php artisan route:list --path=bishop` lists 7 routes.
- A manual smoke test: seed a tenant, create a Constituency Group + assign a Governor LeaderRole, hit `GET /api/v1/governor/dashboard` from Tinker or a curl with a Sanctum token.
- The mobile app can be flipped from `lib/mock.ts` to live API calls (mobile-side change, out of scope here).
