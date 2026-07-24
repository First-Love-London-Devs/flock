# Understanding Campaign Assignment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let a gathering-service-scoped "Understanding Campaign" rep view their first-timers and converts on the Flock app and assign each to a bacenta.

**Architecture:** A new tenant `RoleDefinition` (slug `understanding-campaign`) gates three sanctum + `CheckRole` endpoints under the existing `api/v1` tenant group. Each endpoint resolves the caller's own understanding-campaign `LeaderRole`, takes its `group_id`, and scopes to that group's subtree (`Group::allGroupIds()`). The app adds a two-screen flow (list + assign) reached from the roles screen. "Assign" only sets `understanding_campaigns.allocated_group_id`; nothing is created and the schema is unchanged.

**Tech Stack:** Laravel 11 multi-tenant (stancl/tenancy), Sanctum, PHPUnit feature tests, Filament (unchanged here); Expo Router + TypeScript app.

## Global Constraints

- **Two repos, two branches.** Server: `~/Projects/flock/server`, branch `feat/uc-assignment` (off `main`, already created, holds the spec commit). App: `~/Projects/flock/app`, branch `feat/uc-assignment` (off `master`, create at the first app task). Commit each repo separately; never stage across them.
- **Deploy is server-first.** Server deploys on push to `main` and needs `php artisan tenants:migrate --force` to seed the role into every tenant. The app hits the remote API, so nothing is device-testable until the server is deployed. Do NOT push during the build; merge/deploy is a final step the human approves.
- Role slug is `understanding-campaign` everywhere: `RoleDefinition.slug`, the `CheckRole:understanding-campaign` middleware arg, and the `app/roles.tsx` routing case.
- Assign = set `allocated_group_id` only. No `Member` creation, no follow-up, no edit of captured details, no change to `understanding_campaigns` or the `/welcome` form.
- Bacenta = a `Group` whose `groupType.tracks_attendance` is true. Assignable targets are always `tracks_attendance` groups inside the caller's subtree.
- Server tests: `php artisan test --filter=<Name>`. If it OOMs, use `php -d memory_limit=2G ./vendor/bin/phpunit --filter=<Name>`.
- App validation: `npx tsc --noEmit` from `~/Projects/flock/app`. The baseline is NOT clean on `master` (~25 pre-existing errors); introduce zero NEW errors in files you touch, and diff against the baseline rather than expecting zero total.
- No em dashes in code, comments, or commit messages.
- Commit footers on every commit:
  `Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>`
  `Claude-Session: https://claude.ai/code/session_011qALq4eDJHnFB7Rzp3FNeD`

---

### Task 1: Seed the understanding-campaign role (server)

**Files:**
- Modify: `database/seeders/DefaultRolesSeeder.php` (the `$roles` array, ~line 19-33)
- Create: `database/migrations/tenant/2026_07_24_120000_seed_understanding_campaign_role.php`
- Test: `tests/Feature/Seeders/UnderstandingCampaignRoleSeedTest.php`

**Interfaces:**
- Produces: a `RoleDefinition` row with `slug = 'understanding-campaign'`, `name = 'Understanding Campaign'`, `permission_level = 40`, `applies_to_group_type_id = null`, in every tenant.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Seeders/UnderstandingCampaignRoleSeedTest.php`:

```php
<?php

namespace Tests\Feature\Seeders;

use App\Models\RoleDefinition;
use Database\Seeders\DefaultRolesSeeder;
use Tests\TestCase;

class UnderstandingCampaignRoleSeedTest extends TestCase
{
    public function test_default_roles_seeder_creates_the_understanding_campaign_role(): void
    {
        (new DefaultRolesSeeder())->run();

        $role = RoleDefinition::where('slug', 'understanding-campaign')->first();

        $this->assertNotNull($role);
        $this->assertSame('Understanding Campaign', $role->name);
        $this->assertSame(40, $role->permission_level);
        $this->assertNull($role->applies_to_group_type_id);
    }

    public function test_seeding_twice_does_not_duplicate_the_role(): void
    {
        (new DefaultRolesSeeder())->run();
        (new DefaultRolesSeeder())->run();

        $this->assertSame(1, RoleDefinition::where('slug', 'understanding-campaign')->count());
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=UnderstandingCampaignRoleSeedTest`
Expected: FAIL, the role does not exist yet.

- [ ] **Step 3: Add the role to `DefaultRolesSeeder`**

In `database/seeders/DefaultRolesSeeder.php`, add this entry to the `$roles` array (after the `Admin` line):

```php
            // Field rep who assigns first-timers and converts to a bacenta.
            // applies_to null so it attaches to whichever group is the tenant's
            // gathering service; scope is resolved at runtime from that group.
            ['name' => 'Understanding Campaign', 'slug' => 'understanding-campaign', 'permission_level' => 40, 'applies_to_group_type_id' => null],
```

The existing `foreach (... RoleDefinition::firstOrCreate(['slug' => ...], $role))` makes this idempotent.

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --filter=UnderstandingCampaignRoleSeedTest`
Expected: PASS, 2 tests.

- [ ] **Step 5: Create the tenant migration for existing tenants**

Create `database/migrations/tenant/2026_07_24_120000_seed_understanding_campaign_role.php`:

```php
<?php

use App\Models\RoleDefinition;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        RoleDefinition::firstOrCreate(
            ['slug' => 'understanding-campaign'],
            [
                'name' => 'Understanding Campaign',
                'permission_level' => 40,
                'applies_to_group_type_id' => null,
                'is_active' => true,
            ],
        );
    }

    public function down(): void
    {
        RoleDefinition::where('slug', 'understanding-campaign')->delete();
    }
};
```

- [ ] **Step 6: Run the migration against the test database to confirm it applies cleanly**

Run: `php artisan test --filter=UnderstandingCampaignRoleSeedTest`
Expected: still PASS (the test suite runs tenant migrations in setup; a broken migration would fail here).

- [ ] **Step 7: Commit**

```bash
cd ~/Projects/flock/server
git add database/seeders/DefaultRolesSeeder.php \
        database/migrations/tenant/2026_07_24_120000_seed_understanding_campaign_role.php \
        tests/Feature/Seeders/UnderstandingCampaignRoleSeedTest.php
git commit -m "$(cat <<'EOF'
feat(uc): seed the understanding-campaign role definition

Adds the role to DefaultRolesSeeder for new tenants and a tenant migration
to backfill existing tenants, both idempotent on the slug.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_011qALq4eDJHnFB7Rzp3FNeD
EOF
)"
```

---

### Task 2: List + assignable-groups endpoints (server, read side)

**Files:**
- Create: `app/Http/Controllers/Api/UnderstandingCampaignController.php`
- Modify: `routes/tenant.php` (add a route group after the `admin` prefix group, ~line 137)
- Test: `tests/Feature/Api/UnderstandingCampaignAssignmentTest.php`

**Interfaces:**
- Consumes: the role from Task 1.
- Produces:
  - `GET /api/v1/understanding-campaigns?status=unassigned|assigned` → `{ success, data: [ { id, first_name, last_name, first_time, re_dedicating, attended_on, who_invited, phone_number, stream: {id,name}|null, allocated_group: {id,name}|null } ] }`
  - `GET /api/v1/understanding-campaigns/assignable-groups` → `{ success, data: [ { id, name } ] }`
  - protected method `resolveScopeGroup(Request): Group` on the controller, reused by Task 3.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Api/UnderstandingCampaignAssignmentTest.php`:

```php
<?php

namespace Tests\Feature\Api;

use App\Models\Group;
use App\Models\GroupType;
use App\Models\Leader;
use App\Models\LeaderRole;
use App\Models\RoleDefinition;
use App\Models\UnderstandingCampaign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnderstandingCampaignAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private RoleDefinition $ucRole;
    private GroupType $gsType;
    private GroupType $bacentaType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ucRole = RoleDefinition::create([
            'name' => 'Understanding Campaign', 'slug' => 'understanding-campaign',
            'permission_level' => 40, 'applies_to_group_type_id' => null, 'is_active' => true,
        ]);
        $this->gsType = GroupType::create(['name' => 'Gathering Service', 'slug' => 'gs', 'level' => 1, 'tracks_attendance' => false, 'is_active' => true]);
        $this->bacentaType = GroupType::create(['name' => 'Bacenta', 'slug' => 'bacenta', 'level' => 2, 'tracks_attendance' => true, 'is_active' => true]);
    }

    private function gatheringService(string $name = 'GS A'): Group
    {
        return Group::create(['name' => $name, 'group_type_id' => $this->gsType->id, 'parent_id' => null]);
    }

    private function bacenta(Group $parent, string $name): Group
    {
        return Group::create(['name' => $name, 'group_type_id' => $this->bacentaType->id, 'parent_id' => $parent->id]);
    }

    private function repFor(Group $gs): Leader
    {
        $leader = Leader::factory()->create();
        LeaderRole::create(['leader_id' => $leader->id, 'role_definition_id' => $this->ucRole->id, 'group_id' => $gs->id, 'is_active' => true]);
        return $leader;
    }

    private function record(Group $stream, array $overrides = []): UnderstandingCampaign
    {
        return UnderstandingCampaign::create(array_merge([
            'stream_id' => $stream->id, 'attended_on' => now()->toDateString(),
            'first_name' => 'Ama', 'last_name' => 'Owusu', 'street_name' => '1 High St',
            'postal_code' => 'AB1 2CD', 'phone_number' => '07000000000',
            'first_time' => true, 're_dedicating' => false, 'who_invited' => 'A Friend',
        ], $overrides));
    }

    public function test_rep_sees_only_records_in_their_gathering_service_subtree(): void
    {
        $gs = $this->gatheringService('GS A');
        $bacenta = $this->bacenta($gs, 'Bacenta 1');
        $mine = $this->record($bacenta);

        $otherGs = $this->gatheringService('GS B');
        $otherBacenta = $this->bacenta($otherGs, 'Bacenta 2');
        $this->record($otherBacenta, ['first_name' => 'NotMine']);

        $rep = $this->repFor($gs);

        $res = $this->actingAs($rep, 'sanctum')->getJson('/api/v1/understanding-campaigns');
        $res->assertOk();
        $ids = collect($res->json('data'))->pluck('id')->all();
        $this->assertSame([$mine->id], $ids);
    }

    public function test_unassigned_status_filter_excludes_already_assigned(): void
    {
        $gs = $this->gatheringService();
        $b1 = $this->bacenta($gs, 'B1');
        $b2 = $this->bacenta($gs, 'B2');
        $unassigned = $this->record($b1);
        $assigned = $this->record($b1, ['allocated_group_id' => $b2->id, 'first_name' => 'Done']);
        $rep = $this->repFor($gs);

        $res = $this->actingAs($rep, 'sanctum')->getJson('/api/v1/understanding-campaigns?status=unassigned');
        $res->assertOk();
        $ids = collect($res->json('data'))->pluck('id')->all();
        $this->assertcontains($unassigned->id, $ids);
        $this->assertNotContains($assigned->id, $ids);
    }

    public function test_assignable_groups_returns_only_tracks_attendance_groups_in_subtree(): void
    {
        $gs = $this->gatheringService();
        $bacenta = $this->bacenta($gs, 'Real Bacenta');
        // a non-tracks_attendance child should be excluded
        $subService = Group::create(['name' => 'Sub', 'group_type_id' => $this->gsType->id, 'parent_id' => $gs->id]);
        $rep = $this->repFor($gs);

        $res = $this->actingAs($rep, 'sanctum')->getJson('/api/v1/understanding-campaigns/assignable-groups');
        $res->assertOk();
        $ids = collect($res->json('data'))->pluck('id')->all();
        $this->assertSame([$bacenta->id], $ids);
    }

    public function test_leader_without_the_role_is_forbidden(): void
    {
        $leader = Leader::factory()->create();
        $this->actingAs($leader, 'sanctum')
            ->getJson('/api/v1/understanding-campaigns')
            ->assertForbidden();
    }
}
```

Note: `assertcontains`/`assertNotContains` are real PHPUnit assertions; keep the exact casing `assertContains` / `assertNotContains`. (Fix the lowercase `assertcontains` above to `assertContains` when transcribing.)

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=UnderstandingCampaignAssignmentTest`
Expected: FAIL, routes and controller do not exist (404/500).

- [ ] **Step 3: Create the controller**

Create `app/Http/Controllers/Api/UnderstandingCampaignController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\UnderstandingCampaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnderstandingCampaignController extends Controller
{
    /**
     * The gathering-service group this rep is scoped to, resolved from their
     * own understanding-campaign role rather than the union of all their roles.
     */
    protected function resolveScopeGroup(Request $request): Group
    {
        $role = $request->user()->leaderRoles()
            ->where('is_active', true)
            ->whereNotNull('group_id')
            ->whereHas('roleDefinition', fn ($q) => $q->whereRaw('LOWER(slug) = ?', ['understanding-campaign']))
            ->with('group')
            ->first();

        if (! $role || ! $role->group) {
            abort(response()->json(['success' => false, 'message' => 'no gathering service assigned'], 403));
        }

        return $role->group;
    }

    public function index(Request $request): JsonResponse
    {
        $scopeIds = $this->resolveScopeGroup($request)->allGroupIds();

        $query = UnderstandingCampaign::query()
            ->whereIn('stream_id', $scopeIds)
            ->with(['stream:id,name', 'allocatedGroup:id,name'])
            ->orderByDesc('attended_on')
            ->orderByDesc('id');

        if ($request->query('status') === 'unassigned') {
            $query->whereNull('allocated_group_id');
        } elseif ($request->query('status') === 'assigned') {
            $query->whereNotNull('allocated_group_id');
        }

        $data = $query->get()->map(fn (UnderstandingCampaign $r) => [
            'id' => $r->id,
            'first_name' => $r->first_name,
            'last_name' => $r->last_name,
            'first_time' => (bool) $r->first_time,
            're_dedicating' => (bool) $r->re_dedicating,
            'attended_on' => optional($r->attended_on)->toDateString(),
            'who_invited' => $r->who_invited,
            'phone_number' => $r->phone_number,
            'stream' => $r->stream ? ['id' => $r->stream->id, 'name' => $r->stream->name] : null,
            'allocated_group' => $r->allocatedGroup ? ['id' => $r->allocatedGroup->id, 'name' => $r->allocatedGroup->name] : null,
        ]);

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function assignableGroups(Request $request): JsonResponse
    {
        $scopeIds = $this->resolveScopeGroup($request)->allGroupIds();

        $groups = Group::query()
            ->whereIn('id', $scopeIds)
            ->whereHas('groupType', fn ($q) => $q->where('tracks_attendance', true))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Group $g) => ['id' => $g->id, 'name' => $g->name]);

        return response()->json(['success' => true, 'data' => $groups]);
    }
}
```

- [ ] **Step 4: Register the routes**

In `routes/tenant.php`, inside the `auth:sanctum` + `InitializeLeaderScope` group, add after the `admin` prefix group (near line 137). Add the controller import at the top with the other `App\Http\Controllers\Api\...` imports:

```php
use App\Http\Controllers\Api\UnderstandingCampaignController;
```

```php
        Route::prefix('understanding-campaigns')->middleware([CheckRole::class.':understanding-campaign'])->group(function () {
            Route::get('/', [UnderstandingCampaignController::class, 'index']);
            Route::get('/assignable-groups', [UnderstandingCampaignController::class, 'assignableGroups']);
        });
```

Register `/assignable-groups` BEFORE any `/{id}` route (Task 3 adds `/{id}/assign`, which is fine since it is more specific, but keep `assignable-groups` as a static segment above dynamic ones).

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test --filter=UnderstandingCampaignAssignmentTest`
Expected: the four tests in this file PASS. (Fix the `assertContains` casing noted in Step 1 if not already.)

- [ ] **Step 6: Commit**

```bash
cd ~/Projects/flock/server
git add app/Http/Controllers/Api/UnderstandingCampaignController.php routes/tenant.php \
        tests/Feature/Api/UnderstandingCampaignAssignmentTest.php
git commit -m "$(cat <<'EOF'
feat(uc): list and assignable-groups endpoints for reps

Scopes both to the caller's own understanding-campaign role group subtree,
gated by CheckRole. Read side only; assign follows next.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_011qALq4eDJHnFB7Rzp3FNeD
EOF
)"
```

---

### Task 3: Assign endpoint + request validation (server, write side)

**Files:**
- Create: `app/Http/Requests/AssignUnderstandingCampaignRequest.php`
- Modify: `app/Http/Controllers/Api/UnderstandingCampaignController.php` (add `assign` method)
- Modify: `routes/tenant.php` (add the assign route in the same group)
- Modify: `tests/Feature/Api/UnderstandingCampaignAssignmentTest.php` (add assign tests)

**Interfaces:**
- Consumes: `resolveScopeGroup` and the routes/tests from Task 2.
- Produces: `PATCH /api/v1/understanding-campaigns/{id}/assign` body `{ allocated_group_id: int|null }` → `{ success, data: <same record shape as index rows> }`.

- [ ] **Step 1: Add the failing assign tests**

Append these methods to `tests/Feature/Api/UnderstandingCampaignAssignmentTest.php`:

```php
    public function test_rep_can_assign_an_in_scope_record_to_an_in_scope_bacenta(): void
    {
        $gs = $this->gatheringService();
        $b1 = $this->bacenta($gs, 'B1');
        $target = $this->bacenta($gs, 'Target');
        $record = $this->record($b1);
        $rep = $this->repFor($gs);

        $res = $this->actingAs($rep, 'sanctum')
            ->patchJson("/api/v1/understanding-campaigns/{$record->id}/assign", ['allocated_group_id' => $target->id]);

        $res->assertOk();
        $this->assertSame($target->id, $res->json('data.allocated_group.id'));
        $this->assertDatabaseHas('understanding_campaigns', ['id' => $record->id, 'allocated_group_id' => $target->id]);
    }

    public function test_null_clears_the_assignment(): void
    {
        $gs = $this->gatheringService();
        $b1 = $this->bacenta($gs, 'B1');
        $record = $this->record($b1, ['allocated_group_id' => $b1->id]);
        $rep = $this->repFor($gs);

        $this->actingAs($rep, 'sanctum')
            ->patchJson("/api/v1/understanding-campaigns/{$record->id}/assign", ['allocated_group_id' => null])
            ->assertOk();
        $this->assertDatabaseHas('understanding_campaigns', ['id' => $record->id, 'allocated_group_id' => null]);
    }

    public function test_cannot_assign_a_record_outside_the_reps_subtree(): void
    {
        $gs = $this->gatheringService('GS A');
        $target = $this->bacenta($gs, 'Target');
        $rep = $this->repFor($gs);

        $otherGs = $this->gatheringService('GS B');
        $otherBacenta = $this->bacenta($otherGs, 'Other');
        $foreign = $this->record($otherBacenta);

        $this->actingAs($rep, 'sanctum')
            ->patchJson("/api/v1/understanding-campaigns/{$foreign->id}/assign", ['allocated_group_id' => $target->id])
            ->assertForbidden();
    }

    public function test_cannot_assign_into_a_group_outside_the_subtree(): void
    {
        $gs = $this->gatheringService('GS A');
        $b1 = $this->bacenta($gs, 'B1');
        $record = $this->record($b1);
        $rep = $this->repFor($gs);

        $otherGs = $this->gatheringService('GS B');
        $foreignBacenta = $this->bacenta($otherGs, 'Foreign');

        $this->actingAs($rep, 'sanctum')
            ->patchJson("/api/v1/understanding-campaigns/{$record->id}/assign", ['allocated_group_id' => $foreignBacenta->id])
            ->assertStatus(422);
    }

    public function test_cannot_assign_into_a_non_tracks_attendance_group(): void
    {
        $gs = $this->gatheringService();
        $b1 = $this->bacenta($gs, 'B1');
        $record = $this->record($b1);
        $nonBacenta = Group::create(['name' => 'Sub GS', 'group_type_id' => $this->gsType->id, 'parent_id' => $gs->id]);
        $rep = $this->repFor($gs);

        $this->actingAs($rep, 'sanctum')
            ->patchJson("/api/v1/understanding-campaigns/{$record->id}/assign", ['allocated_group_id' => $nonBacenta->id])
            ->assertStatus(422);
    }
```

- [ ] **Step 2: Run to verify the new tests fail**

Run: `php artisan test --filter=UnderstandingCampaignAssignmentTest`
Expected: the five new tests FAIL (route missing), the Task 2 tests still PASS.

- [ ] **Step 3: Extract the row shape into a `present()` helper first**

Before adding `assign`, refactor Task 2's `index` so the row array is built by a private method, so both endpoints stay DRY. In `UnderstandingCampaignController`, add:

```php
    private function present(UnderstandingCampaign $r): array
    {
        return [
            'id' => $r->id,
            'first_name' => $r->first_name,
            'last_name' => $r->last_name,
            'first_time' => (bool) $r->first_time,
            're_dedicating' => (bool) $r->re_dedicating,
            'attended_on' => optional($r->attended_on)->toDateString(),
            'who_invited' => $r->who_invited,
            'phone_number' => $r->phone_number,
            'stream' => $r->stream ? ['id' => $r->stream->id, 'name' => $r->stream->name] : null,
            'allocated_group' => $r->allocatedGroup ? ['id' => $r->allocatedGroup->id, 'name' => $r->allocatedGroup->name] : null,
        ];
    }
```

and change `index`'s `->map(...)` to `->map(fn (UnderstandingCampaign $r) => $this->present($r))`.

- [ ] **Step 4: Create the FormRequest**

The FormRequest resolves the caller's scope itself (it has `$this->user()`), so authorization and the target-group rule are fully self-contained and the controller stays thin. Create `app/Http/Requests/AssignUnderstandingCampaignRequest.php`:

```php
<?php

namespace App\Http\Requests;

use App\Models\Group;
use App\Models\UnderstandingCampaign;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class AssignUnderstandingCampaignRequest extends FormRequest
{
    private function scopeGroupIds(): Collection
    {
        $role = $this->user()?->leaderRoles()
            ->where('is_active', true)
            ->whereNotNull('group_id')
            ->whereHas('roleDefinition', fn ($q) => $q->whereRaw('LOWER(slug) = ?', ['understanding-campaign']))
            ->with('group')
            ->first();

        return $role && $role->group ? $role->group->allGroupIds() : collect();
    }

    public function authorize(): bool
    {
        // The record must be inside the rep's own gathering-service subtree.
        $record = UnderstandingCampaign::find($this->route('id'));

        return $record !== null && $this->scopeGroupIds()->contains($record->stream_id);
    }

    public function rules(): array
    {
        $scopeIds = $this->scopeGroupIds()->all();

        return [
            'allocated_group_id' => [
                'nullable',
                'integer',
                // Target must be a group inside the subtree ...
                Rule::exists('groups', 'id')->where(fn ($q) => $q->whereIn('id', $scopeIds ?: [0])),
                // ... and it must be a bacenta (tracks_attendance).
                function ($attribute, $value, $fail) {
                    if ($value === null) {
                        return;
                    }
                    $group = Group::with('groupType')->find($value);
                    if (! $group || ! optional($group->groupType)->tracks_attendance) {
                        $fail('The selected group is not a bacenta.');
                    }
                },
            ],
        ];
    }
}
```

A failed `authorize()` returns 403; a failed rule returns 422. That is what the two negative tests assert.

- [ ] **Step 5: Add the `assign` method to the controller**

Import the request at the top of `UnderstandingCampaignController`:

```php
use App\Http\Requests\AssignUnderstandingCampaignRequest;
```

Then add the method (thin, because the FormRequest already enforced authz + target validity):

```php
    public function assign(AssignUnderstandingCampaignRequest $request, int $id): JsonResponse
    {
        $scopeIds = $this->resolveScopeGroup($request)->allGroupIds();
        $record = UnderstandingCampaign::whereIn('stream_id', $scopeIds)->findOrFail($id);
        $record->update(['allocated_group_id' => $request->validated()['allocated_group_id'] ?? null]);
        $record->load(['stream:id,name', 'allocatedGroup:id,name']);

        return response()->json(['success' => true, 'data' => $this->present($record)]);
    }
```

- [ ] **Step 6: Add the assign route**

In `routes/tenant.php`, inside the `understanding-campaigns` prefix group from Task 2, add:

```php
            Route::patch('/{id}/assign', [UnderstandingCampaignController::class, 'assign'])->whereNumber('id');
```

- [ ] **Step 7: Run the full test file**

Run: `php artisan test --filter=UnderstandingCampaignAssignmentTest`
Expected: all nine tests PASS.

- [ ] **Step 8: Run the neighbouring suites for regressions**

Run: `php artisan test --filter=CheckRole` then `php artisan test --filter=UnderstandingCampaign`
Expected: no new failures.

- [ ] **Step 9: Commit**

```bash
cd ~/Projects/flock/server
git add app/Http/Requests/AssignUnderstandingCampaignRequest.php \
        app/Http/Controllers/Api/UnderstandingCampaignController.php routes/tenant.php \
        tests/Feature/Api/UnderstandingCampaignAssignmentTest.php
git commit -m "$(cat <<'EOF'
feat(uc): assign endpoint with scope-enforced validation

A rep can only assign a record in their subtree, only into a bacenta in
their subtree; null clears it. Authz and target checks live in a FormRequest.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_011qALq4eDJHnFB7Rzp3FNeD
EOF
)"
```

---

### Task 4: App API client + types

**Files:**
- Modify: `~/Projects/flock/app/lib/api.ts` (add a `campaign` export and types)

**Interfaces:**
- Consumes: the three endpoints from Tasks 2-3.
- Produces: `campaign.list(status?)`, `campaign.assignableGroups()`, `campaign.assign(id, groupId|null)`, and exported types `CampaignRecord`, `AssignableGroup`.

- [ ] **Step 1: Create the app branch**

```bash
cd ~/Projects/flock/app
git checkout master && git checkout -b feat/uc-assignment
```

- [ ] **Step 2: Add types and the client**

Append to `lib/api.ts`, matching the existing `members` object style (a `request<T>` call returning `{ success, data }`):

```ts
// ─── Understanding Campaign ─────────────────────────────────────────────────

export interface CampaignRecord {
  id: number;
  first_name: string;
  last_name: string;
  first_time: boolean;
  re_dedicating: boolean;
  attended_on: string | null;
  who_invited: string | null;
  phone_number: string | null;
  stream: { id: number; name: string } | null;
  allocated_group: { id: number; name: string } | null;
}

export interface AssignableGroup {
  id: number;
  name: string;
}

export const campaign = {
  list: (status?: 'unassigned' | 'assigned') => {
    const qs = status ? `?status=${status}` : '';
    return request<{ success: boolean; data: CampaignRecord[] }>(`/understanding-campaigns${qs}`);
  },
  assignableGroups: () =>
    request<{ success: boolean; data: AssignableGroup[] }>(`/understanding-campaigns/assignable-groups`),
  assign: (id: number, allocated_group_id: number | null) =>
    request<{ success: boolean; data: CampaignRecord }>(`/understanding-campaigns/${id}/assign`, {
      method: 'PATCH',
      body: JSON.stringify({ allocated_group_id }),
    }),
};
```

- [ ] **Step 3: Typecheck**

Run: `cd ~/Projects/flock/app && npx tsc --noEmit 2>&1 | grep -c "lib/api.ts"`
Expected: `0` (no new errors in the file you touched). The whole-repo count stays at its `master` baseline.

- [ ] **Step 4: Commit**

```bash
cd ~/Projects/flock/app
git add lib/api.ts
git commit -m "$(cat <<'EOF'
feat(uc): app API client for the campaign assignment endpoints

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_011qALq4eDJHnFB7Rzp3FNeD
EOF
)"
```

---

### Task 5: App routing + list screen

**Files:**
- Modify: `~/Projects/flock/app/app/roles.tsx` (add the routing case)
- Create: `~/Projects/flock/app/app/(campaign)/_layout.tsx`
- Create: `~/Projects/flock/app/app/(campaign)/index.tsx`

**Interfaces:**
- Consumes: `campaign.list`, `CampaignRecord`, `useActiveRole` from Task 4 and existing lib.
- Produces: route `/(campaign)/index` reached when the active role slug is `understanding-campaign`; each row links to `/(campaign)/{id}`.

- [ ] **Step 1: Add the routing case**

In `app/roles.tsx`, extend the `href` ternary in `handleRolePress` (currently ends `: '/(app)/home'`):

```tsx
    const href =
      slug === 'bishop'
        ? '/(bishop)/summary'
        : slug === 'admin'
          ? '/(admin)/members'
          : slug === 'understanding-campaign'
            ? '/(campaign)'
            : slug === 'governor' || ['basonta-head', 'basonta-overseer', 'ministry-head'].includes(slug)
              ? '/(governor)/home'
              : '/(app)/home';
```

- [ ] **Step 2: Create the flow layout**

Create `app/(campaign)/_layout.tsx`, mirroring an existing group `_layout.tsx` (e.g. `app/(governor)/_layout.tsx`) with a Stack:

```tsx
import { Stack } from 'expo-router';

export default function CampaignLayout() {
  return (
    <Stack screenOptions={{ headerShown: false }}>
      <Stack.Screen name="index" />
      <Stack.Screen name="[id]" />
    </Stack>
  );
}
```

- [ ] **Step 3: Create the list screen**

Create `app/(campaign)/index.tsx`. Follow the visual patterns of an existing list screen in `app/(governor)/` (theme hook, SafeAreaView, FlatList, loading/empty/error states). The screen:
- has a segmented control with `unassigned` (default) and `assigned`
- fetches via `campaign.list(status)` on mount and on segment change and on pull-to-refresh
- renders each record: `first_name last_name`, a chip reading "First timer" when `first_time` else "Re-dedication" when `re_dedicating`, `attended_on`, `who_invited`
- taps a row to `router.push(\`/(campaign)/${id}\`)`
- shows an explicit empty state ("No one to assign right now" / "Nothing assigned yet") and an inline retry on fetch error

Write it in the style of the neighbouring governor screens; do not invent a new design system. Read one governor list screen first and match its structure and theming.

- [ ] **Step 4: Typecheck**

Run: `cd ~/Projects/flock/app && npx tsc --noEmit 2>&1 | grep -E "app/\(campaign\)|roles.tsx"`
Expected: no lines (no new errors in the touched files).

- [ ] **Step 5: Commit**

```bash
cd ~/Projects/flock/app
git add "app/roles.tsx" "app/(campaign)/_layout.tsx" "app/(campaign)/index.tsx"
git commit -m "$(cat <<'EOF'
feat(uc): campaign flow routing and the first-timers list screen

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_011qALq4eDJHnFB7Rzp3FNeD
EOF
)"
```

---

### Task 6: App detail + assign screen

**Files:**
- Create: `~/Projects/flock/app/app/(campaign)/[id].tsx`

**Interfaces:**
- Consumes: `campaign.assignableGroups`, `campaign.assign`, `CampaignRecord`, `AssignableGroup`.
- Produces: the assign screen at `/(campaign)/{id}`.

- [ ] **Step 1: Create the detail/assign screen**

Create `app/(campaign)/[id].tsx`. It receives the record either via a route param passed from the list (`useLocalSearchParams`) or by refetching the list and finding the id; simplest is to pass the record fields you already have from the list via params, but since the list holds the full record, read the id from params and re-fetch the single record is not available (no show endpoint), so instead: navigate carrying the needed display fields as params, OR keep a lightweight store. Choose the simplest that works: pass the record's display fields as route params from the list row, and on this screen use them for display; use the id for the assign call.

The screen:
- shows the person's name, first-timer/re-dedication, `attended_on`, `who_invited`, `phone_number` (with a tap-to-call using `Linking.openURL(\`tel:...\`)` if a governor screen already does this; otherwise plain text)
- shows current allocation if any
- an "Assign to bacenta" button opens a picker (modal or a simple list) populated by `campaign.assignableGroups()`
- on selecting a bacenta, calls `campaign.assign(id, groupId)`, then `router.back()` so the list reloads on focus
- a re-assign path works identically when already assigned
- explicit loading state while groups load, empty state ("No bacentas set up yet"), and inline error with retry on assign failure

Match the governor screens' theming and components. Read a governor detail screen first.

- [ ] **Step 2: Ensure the list reloads after assigning**

In `app/(campaign)/index.tsx`, refetch on screen focus so a return from the assign screen reflects the move. Use expo-router's `useFocusEffect` with the existing fetch function (mirror how a governor list screen refreshes on focus if it does; otherwise add `useFocusEffect(useCallback(() => { load(); }, [status]))`).

- [ ] **Step 3: Typecheck**

Run: `cd ~/Projects/flock/app && npx tsc --noEmit 2>&1 | grep -E "app/\(campaign\)"`
Expected: no lines.

- [ ] **Step 4: Commit**

```bash
cd ~/Projects/flock/app
git add "app/(campaign)/[id].tsx" "app/(campaign)/index.tsx"
git commit -m "$(cat <<'EOF'
feat(uc): campaign assign screen and list refocus reload

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_011qALq4eDJHnFB7Rzp3FNeD
EOF
)"
```

---

## Final verification and deploy (human-gated)

1. Server suite green: `php artisan test --filter=UnderstandingCampaign` and `--filter=CheckRole`.
2. App typecheck introduces no new errors versus the `master` baseline.
3. Deploy order: merge/push the server to `main`, run `php artisan tenants:migrate --force` to seed the role into every tenant, assign the role to a test rep on a real gathering-service group, confirm the three endpoints against that tenant, THEN OTA the app.
4. Manual pass on device: the role appears on the roles screen, the list scopes to the rep's gathering service, "To assign" and "Assigned" split correctly, assigning a person moves them between the tabs and sets `allocated_group_id`.
