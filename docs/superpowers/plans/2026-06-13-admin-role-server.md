# Admin Role — Server Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add an `admin` role with scoped CRUD endpoints for members and Bacentas (cell-group type groups).

**Architecture:** New `AdminController` follows the same pattern as `BishopController` and `GovernorController`. Scope is determined by BFS over the group tree rooted at the admin's assigned `group_id`, collecting all `cell-group` type descendants. `CheckRole` middleware (already registered as `'role'`) gates every route.

**Tech Stack:** Laravel, Sanctum, Stancl Tenancy, PHPUnit feature tests.

---

## File Map

| File | Action |
|------|--------|
| `database/seeders/DefaultRolesSeeder.php` | Modify — add `admin` role entry |
| `app/Http/Controllers/Api/AdminController.php` | Create — full CRUD for members + bacentas |
| `routes/tenant.php` | Modify — add `/admin` prefix route group |
| `tests/Feature/Api/AdminControllerTest.php` | Create — feature tests |

---

## Task 1: Add Admin Role to Seeder

**Files:**
- Modify: `database/seeders/DefaultRolesSeeder.php`

- [ ] **Step 1: Add the admin role to the `$roles` array**

  Open `database/seeders/DefaultRolesSeeder.php`. Inside the `$roles` array, add after the `ministry-head` entry:

  ```php
  ['name' => 'Admin', 'slug' => 'admin', 'permission_level' => 50, 'applies_to_group_type_id' => null],
  ```

- [ ] **Step 2: Run the seeder to create the role**

  ```bash
  cd ~/Projects/flock/server && php artisan db:seed --class=DefaultRolesSeeder
  ```

  Expected: no errors; existing roles unchanged (seeder uses `firstOrCreate`).

- [ ] **Step 3: Verify the role was created**

  ```bash
  php artisan tinker --execute="echo App\Models\RoleDefinition::where('slug','admin')->value('name');"
  ```

  Expected: `Admin`

- [ ] **Step 4: Commit**

  ```bash
  git add database/seeders/DefaultRolesSeeder.php
  git commit -m "feat: add admin role to DefaultRolesSeeder"
  ```

---

## Task 2: Create AdminController

**Files:**
- Create: `app/Http/Controllers/Api/AdminController.php`

- [ ] **Step 1: Create the controller file**

  Create `app/Http/Controllers/Api/AdminController.php` with this content:

  ```php
  <?php

  namespace App\Http\Controllers\Api;

  use App\Http\Controllers\Controller;
  use App\Models\Group;
  use App\Models\GroupType;
  use App\Models\Member;
  use Illuminate\Http\JsonResponse;
  use Illuminate\Http\Request;

  class AdminController extends Controller
  {
      // ─── Scope helpers ──────────────────────────────────────────────────────

      protected function adminGroupId(Request $request): int
      {
          $role = $request->user()->leaderRoles()
              ->where('is_active', true)
              ->whereNotNull('group_id')
              ->whereHas('roleDefinition', fn ($q) => $q->where('slug', 'admin'))
              ->first();

          abort_if(! $role, response()->json(['success' => false, 'message' => 'Admin group not assigned'], 403));
          return $role->group_id;
      }

      protected function scopedBacentaIds(Request $request): array
      {
          $rootId = $this->adminGroupId($request);
          $cellGroupTypeId = GroupType::where('slug', 'cell-group')->value('id');

          $toVisit = [$rootId];
          $bacentaIds = [];

          while (! empty($toVisit)) {
              $children = Group::whereIn('parent_id', $toVisit)
                  ->where('is_active', true)
                  ->get(['id', 'group_type_id']);

              $toVisit = [];
              foreach ($children as $child) {
                  if ($child->group_type_id === $cellGroupTypeId) {
                      $bacentaIds[] = $child->id;
                  } else {
                      $toVisit[] = $child->id;
                  }
              }
          }

          return $bacentaIds;
      }

      protected function scopedBacenta(Request $request, int $id): Group
      {
          $ids = $this->scopedBacentaIds($request);
          abort_if(! in_array($id, $ids), response()->json(['success' => false, 'message' => 'Bacenta not in scope'], 403));
          return Group::findOrFail($id);
      }

      protected function scopedMember(Request $request, int $id): Member
      {
          $bacentaIds = $this->scopedBacentaIds($request);
          $member = Member::with('groups')->findOrFail($id);
          $inScope = $member->groups->pluck('id')->intersect($bacentaIds)->isNotEmpty();
          abort_if(! $inScope, response()->json(['success' => false, 'message' => 'Member not in scope'], 403));
          return $member;
      }

      // ─── Members ────────────────────────────────────────────────────────────

      public function listMembers(Request $request): JsonResponse
      {
          $bacentaIds = $this->scopedBacentaIds($request);
          $search = $request->query('search');
          $perPage = (int) $request->query('per_page', 25);

          $query = Member::whereHas('groups', fn ($q) => $q->whereIn('groups.id', $bacentaIds))
              ->with(['groups:id,name']);

          if ($search) {
              $query->where(fn ($q) => $q
                  ->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('phone_number', 'like', "%{$search}%")
              );
          }

          return $this->ok($query->paginate($perPage));
      }

      public function showMember(Request $request, int $id): JsonResponse
      {
          return $this->ok($this->scopedMember($request, $id)->load('groups:id,name'));
      }

      public function createMember(Request $request): JsonResponse
      {
          $data = $request->validate([
              'first_name'    => 'required|string|max:100',
              'last_name'     => 'required|string|max:100',
              'phone_number'  => 'nullable|string|max:30',
              'gender'        => 'nullable|string|in:male,female',
              'date_of_birth' => 'nullable|date',
              'member_type'   => 'nullable|string|max:50',
              'bacenta_id'    => 'nullable|integer',
          ]);

          if (! empty($data['bacenta_id'])) {
              $this->scopedBacenta($request, $data['bacenta_id']);
          }

          $member = Member::create([
              'first_name'    => $data['first_name'],
              'last_name'     => $data['last_name'],
              'phone_number'  => $data['phone_number'] ?? null,
              'gender'        => $data['gender'] ?? null,
              'date_of_birth' => $data['date_of_birth'] ?? null,
              'member_type'   => $data['member_type'] ?? null,
              'is_active'     => true,
              'member_since'  => now(),
          ]);

          if (! empty($data['bacenta_id'])) {
              $member->groups()->attach($data['bacenta_id'], [
                  'joined_at'  => now(),
                  'is_primary' => true,
              ]);
          }

          return $this->ok($member->load('groups:id,name'));
      }

      public function updateMember(Request $request, int $id): JsonResponse
      {
          $member = $this->scopedMember($request, $id);

          $data = $request->validate([
              'first_name'    => 'sometimes|string|max:100',
              'last_name'     => 'sometimes|string|max:100',
              'phone_number'  => 'sometimes|nullable|string|max:30',
              'gender'        => 'sometimes|nullable|string|in:male,female',
              'date_of_birth' => 'sometimes|nullable|date',
              'member_type'   => 'sometimes|nullable|string|max:50',
              'is_active'     => 'sometimes|boolean',
          ]);

          $member->update($data);

          return $this->ok($member->fresh()->load('groups:id,name'));
      }

      public function deactivateMember(Request $request, int $id): JsonResponse
      {
          $this->scopedMember($request, $id)->update(['is_active' => false]);
          return $this->ok(['message' => 'Member deactivated']);
      }

      // ─── Bacentas ───────────────────────────────────────────────────────────

      public function listBacentas(Request $request): JsonResponse
      {
          $bacentaIds = $this->scopedBacentaIds($request);
          $search = $request->query('search');

          $query = Group::whereIn('id', $bacentaIds)->withCount('members');

          if ($search) {
              $query->where('name', 'like', "%{$search}%");
          }

          return $this->ok($query->get());
      }

      public function showBacenta(Request $request, int $id): JsonResponse
      {
          return $this->ok($this->scopedBacenta($request, $id)->loadCount('members'));
      }

      public function createBacenta(Request $request): JsonResponse
      {
          $data = $request->validate(['name' => 'required|string|max:150']);

          $parentId = $this->adminGroupId($request);
          $cellGroupTypeId = GroupType::where('slug', 'cell-group')->value('id');

          $bacenta = Group::create([
              'name'          => $data['name'],
              'parent_id'     => $parentId,
              'group_type_id' => $cellGroupTypeId,
              'is_active'     => true,
          ]);

          return $this->ok($bacenta->loadCount('members'));
      }

      public function updateBacenta(Request $request, int $id): JsonResponse
      {
          $bacenta = $this->scopedBacenta($request, $id);

          $data = $request->validate([
              'name'      => 'sometimes|string|max:150',
              'is_active' => 'sometimes|boolean',
          ]);

          $bacenta->update($data);

          return $this->ok($bacenta->fresh()->loadCount('members'));
      }

      public function deactivateBacenta(Request $request, int $id): JsonResponse
      {
          $this->scopedBacenta($request, $id)->update(['is_active' => false]);
          return $this->ok(['message' => 'Bacenta deactivated']);
      }

      protected function ok(mixed $data): JsonResponse
      {
          return response()->json(['success' => true, 'data' => $data]);
      }
  }
  ```

- [ ] **Step 2: Verify PHP syntax**

  ```bash
  cd ~/Projects/flock/server && php -l app/Http/Controllers/Api/AdminController.php
  ```

  Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

  ```bash
  git add app/Http/Controllers/Api/AdminController.php
  git commit -m "feat: add AdminController with scoped member and bacenta CRUD"
  ```

---

## Task 3: Add Admin Routes

**Files:**
- Modify: `routes/tenant.php`

- [ ] **Step 1: Add the admin route group after the bishop routes block**

  In `routes/tenant.php`, find the bishop route group (around line 89). Add immediately after its closing `});`:

  ```php
  Route::prefix('admin')
      ->middleware([\App\Http\Middleware\CheckRole::class . ':admin'])
      ->group(function () {
          Route::get('members',        [App\Http\Controllers\Api\AdminController::class, 'listMembers']);
          Route::get('members/{id}',   [App\Http\Controllers\Api\AdminController::class, 'showMember'])->whereNumber('id');
          Route::post('members',       [App\Http\Controllers\Api\AdminController::class, 'createMember']);
          Route::put('members/{id}',   [App\Http\Controllers\Api\AdminController::class, 'updateMember'])->whereNumber('id');
          Route::delete('members/{id}',[App\Http\Controllers\Api\AdminController::class, 'deactivateMember'])->whereNumber('id');

          Route::get('bacentas',        [App\Http\Controllers\Api\AdminController::class, 'listBacentas']);
          Route::get('bacentas/{id}',   [App\Http\Controllers\Api\AdminController::class, 'showBacenta'])->whereNumber('id');
          Route::post('bacentas',       [App\Http\Controllers\Api\AdminController::class, 'createBacenta']);
          Route::put('bacentas/{id}',   [App\Http\Controllers\Api\AdminController::class, 'updateBacenta'])->whereNumber('id');
          Route::delete('bacentas/{id}',[App\Http\Controllers\Api\AdminController::class, 'deactivateBacenta'])->whereNumber('id');
      });
  ```

- [ ] **Step 2: Verify routes are registered**

  ```bash
  cd ~/Projects/flock/server && php artisan route:list --path=admin
  ```

  Expected: 10 routes listed under `/api/v1/admin/members` and `/api/v1/admin/bacentas`.

- [ ] **Step 3: Commit**

  ```bash
  git add routes/tenant.php
  git commit -m "feat: add admin routes for members and bacentas"
  ```

---

## Task 4: Feature Tests

**Files:**
- Create: `tests/Feature/Api/AdminControllerTest.php`

- [ ] **Step 1: Create the test file**

  Create `tests/Feature/Api/AdminControllerTest.php`:

  ```php
  <?php

  namespace Tests\Feature\Api;

  use App\Models\Group;
  use App\Models\GroupType;
  use App\Models\Leader;
  use App\Models\LeaderRole;
  use App\Models\Member;
  use App\Models\RoleDefinition;
  use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
  use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
  use Tests\Concerns\BuildsGovernanceFixtures;
  use Tests\TestCase;

  class AdminControllerTest extends TestCase
  {
      use BuildsGovernanceFixtures;

      private RoleDefinition $adminRole;
      private Group $constituency;
      private Group $bacenta;
      private Leader $admin;

      protected function setUp(): void
      {
          parent::setUp();
          $this->withoutMiddleware([
              InitializeTenancyByDomain::class,
              PreventAccessFromCentralDomains::class,
          ]);
          $this->seedGovernanceTypes();

          $this->adminRole = RoleDefinition::factory()->create([
              'name' => 'Admin',
              'slug' => 'admin',
              'permission_level' => 50,
              'applies_to_group_type_id' => null,
          ]);

          $this->constituency = $this->makeConstituency('Test Constituency');
          $this->bacenta = $this->makeCellGroup($this->constituency, null, 'Test Bacenta');
          $this->admin = $this->makeAdmin($this->constituency);
      }

      private function makeAdmin(Group $group): Leader
      {
          $leader = Leader::factory()->create();
          LeaderRole::factory()->create([
              'leader_id'          => $leader->id,
              'role_definition_id' => $this->adminRole->id,
              'group_id'           => $group->id,
              'is_active'          => true,
          ]);
          return $leader;
      }

      // ─── Auth guards ────────────────────────────────────────────────────────

      public function test_unauthenticated_is_rejected(): void
      {
          $this->getJson('/api/v1/admin/members')->assertStatus(401);
      }

      public function test_wrong_role_is_rejected(): void
      {
          $bishop = $this->makeBishop();
          $this->actingAs($bishop, 'sanctum')
              ->getJson('/api/v1/admin/members')
              ->assertStatus(403);
      }

      // ─── Members ────────────────────────────────────────────────────────────

      public function test_list_members_returns_only_scoped_members(): void
      {
          $inScope = $this->makeMember($this->bacenta);

          $otherConstituency = $this->makeConstituency('Other');
          $otherBacenta = $this->makeCellGroup($otherConstituency);
          $outOfScope = $this->makeMember($otherBacenta);

          $data = $this->actingAs($this->admin, 'sanctum')
              ->getJson('/api/v1/admin/members')
              ->assertOk()
              ->json('data.data');

          $ids = collect($data)->pluck('id');
          $this->assertTrue($ids->contains($inScope->id));
          $this->assertFalse($ids->contains($outOfScope->id));
      }

      public function test_create_member_without_bacenta(): void
      {
          $r = $this->actingAs($this->admin, 'sanctum')
              ->postJson('/api/v1/admin/members', [
                  'first_name' => 'Jane',
                  'last_name'  => 'Doe',
              ])
              ->assertOk();

          $this->assertEquals('Jane', $r->json('data.first_name'));
          $this->assertTrue($r->json('data.is_active'));
      }

      public function test_create_member_assigns_to_bacenta(): void
      {
          $r = $this->actingAs($this->admin, 'sanctum')
              ->postJson('/api/v1/admin/members', [
                  'first_name' => 'John',
                  'last_name'  => 'Smith',
                  'bacenta_id' => $this->bacenta->id,
              ])
              ->assertOk();

          $memberId = $r->json('data.id');
          $this->assertDatabaseHas('group_member', [
              'member_id' => $memberId,
              'group_id'  => $this->bacenta->id,
          ]);
      }

      public function test_create_member_rejects_out_of_scope_bacenta(): void
      {
          $otherBacenta = $this->makeCellGroup($this->makeConstituency('Other'));

          $this->actingAs($this->admin, 'sanctum')
              ->postJson('/api/v1/admin/members', [
                  'first_name' => 'Test',
                  'last_name'  => 'Person',
                  'bacenta_id' => $otherBacenta->id,
              ])
              ->assertStatus(403);
      }

      public function test_update_member(): void
      {
          $member = $this->makeMember($this->bacenta);

          $this->actingAs($this->admin, 'sanctum')
              ->putJson("/api/v1/admin/members/{$member->id}", ['first_name' => 'Updated'])
              ->assertOk()
              ->assertJsonPath('data.first_name', 'Updated');
      }

      public function test_deactivate_member(): void
      {
          $member = $this->makeMember($this->bacenta);

          $this->actingAs($this->admin, 'sanctum')
              ->deleteJson("/api/v1/admin/members/{$member->id}")
              ->assertOk();

          $this->assertFalse($member->fresh()->is_active);
      }

      public function test_cannot_access_out_of_scope_member(): void
      {
          $other = $this->makeMember($this->makeCellGroup($this->makeConstituency('X')));

          $this->actingAs($this->admin, 'sanctum')
              ->getJson("/api/v1/admin/members/{$other->id}")
              ->assertStatus(403);
      }

      // ─── Bacentas ───────────────────────────────────────────────────────────

      public function test_list_bacentas_returns_only_scoped_bacentas(): void
      {
          $other = $this->makeCellGroup($this->makeConstituency('Other'));

          $data = $this->actingAs($this->admin, 'sanctum')
              ->getJson('/api/v1/admin/bacentas')
              ->assertOk()
              ->json('data');

          $ids = collect($data)->pluck('id');
          $this->assertTrue($ids->contains($this->bacenta->id));
          $this->assertFalse($ids->contains($other->id));
      }

      public function test_create_bacenta(): void
      {
          $r = $this->actingAs($this->admin, 'sanctum')
              ->postJson('/api/v1/admin/bacentas', ['name' => 'New Bacenta'])
              ->assertOk();

          $this->assertEquals('New Bacenta', $r->json('data.name'));
          $this->assertEquals($this->constituency->id, Group::find($r->json('data.id'))->parent_id);
      }

      public function test_update_bacenta(): void
      {
          $this->actingAs($this->admin, 'sanctum')
              ->putJson("/api/v1/admin/bacentas/{$this->bacenta->id}", ['name' => 'Renamed'])
              ->assertOk()
              ->assertJsonPath('data.name', 'Renamed');
      }

      public function test_deactivate_bacenta(): void
      {
          $this->actingAs($this->admin, 'sanctum')
              ->deleteJson("/api/v1/admin/bacentas/{$this->bacenta->id}")
              ->assertOk();

          $this->assertFalse($this->bacenta->fresh()->is_active);
      }

      public function test_cannot_access_out_of_scope_bacenta(): void
      {
          $other = $this->makeCellGroup($this->makeConstituency('X'));

          $this->actingAs($this->admin, 'sanctum')
              ->getJson("/api/v1/admin/bacentas/{$other->id}")
              ->assertStatus(403);
      }
  }
  ```

- [ ] **Step 2: Run the tests**

  ```bash
  cd ~/Projects/flock/server && php artisan test tests/Feature/Api/AdminControllerTest.php --stop-on-failure
  ```

  Expected: all tests pass (green).

- [ ] **Step 3: Commit**

  ```bash
  git add tests/Feature/Api/AdminControllerTest.php
  git commit -m "test: add AdminController feature tests"
  ```

---

## Task 5: Deploy to Forge

- [ ] **Step 1: Push to remote**

  ```bash
  cd ~/Projects/flock/server && git push
  ```

- [ ] **Step 2: Trigger deploy on Forge**

  The server uses Forge Quick Deploy — push triggers auto-deploy. Confirm in Forge dashboard that deployment completes successfully.

- [ ] **Step 3: Verify the new route is live**

  ```bash
  curl -s -o /dev/null -w "%{http_code}" https://<subdomain>.church-stack.com/api/v1/admin/members
  ```

  Expected: `401` (unauthenticated — route exists but auth guard is working).
