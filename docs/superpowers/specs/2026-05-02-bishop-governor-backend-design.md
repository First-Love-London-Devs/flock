# Bishop & Governor Backend

## Problem

The mobile app (`~/Projects/flock/app`) recently shipped Bishop and Governor role screens (PR #11, commit `b0790df`) backed entirely by mock data in `lib/mock.ts`. The app's `lib/api.ts` already declares 12 endpoints under `governor.*` and `bishop.*` that the backend does not yet implement. We need to wire those endpoints to real data so the screens stop reading mocks.

## Goals

- Implement the 12 endpoints declared in `lib/api.ts` against real tenant data.
- Add `bishop` and `governor` roles to the role catalog so leaders can be assigned to them.
- Add a "Constituency" GroupType so Cell Groups can be parented to a Constituency overseen by a Governor.
- Existing attendance / dashboard / member flows (Cell Leader, District Pastor, Zone Overseer) remain untouched.
- All endpoints are read-only; tenant operators set up Bishops/Governors/Constituencies via seeders or Tinker for now.

## Non-goals

- Admin UI (Filament/Inertia) for managing Constituencies, Bishop/Governor assignments. Out of scope; deferred.
- Mobile-side write actions from Bishop/Governor screens (assign governor, create constituency, etc.).
- Multi-Bishop / Diocese model. Designed not to paint into a corner, but only single-Bishop-per-tenant is implemented.
- Adding a `service_type` column to `attendance_summaries`. Sunday vs Midweek is derived from `date`'s day-of-week.
- Caching, performance optimization. Add when measured need exists.
- Changes to existing controllers (Dashboard, Member, Group, Attendance).

## Decisions (from brainstorming)

| Decision | Chosen |
|---|---|
| Bishop / Governor are existing roles or new? | New roles, new GroupType |
| Hierarchy | Bishop → Governor → Constituency → existing Cell Group → members |
| Bishop scope | One per tenant; design accommodates future Diocese without app changes |
| Scope of work | Read-only API + seeders only |
| Leaf groups under Constituency | Reuse existing Cell Group GroupType |
| Sunday vs Midweek | Derived from `date->dayOfWeek` — no schema change |
| Endpoint organization | Two role-namespaced controllers + shared `ConstituencyAnalytics` service |

## Architecture

Three small, focused units:

- **`App\Http\Controllers\Api\GovernorController`** — 5 endpoints. Resolves the authenticated Governor's Constituency, delegates to `ConstituencyAnalytics`, wraps responses in `{ success, data }`.
- **`App\Http\Controllers\Api\BishopController`** — 7 endpoints. Validates `govId` route params, resolves the corresponding Constituency, delegates to the same service. Tenant-wide variants for top-level Bishop views.
- **`App\Services\Governance\ConstituencyAnalytics`** — single class containing every aggregation query. Methods take a `Group $constituency` (or none for tenant-wide), return arrays / `LengthAwarePaginator`. No Request/Response coupling.

Existing `CheckRole` middleware gates each route group. One small fix to `CheckRole` is included (see below) so it consults `LeaderRole` rather than the legacy `user_type`.

No schema migrations. Seeder additions only.

## Data model

### New role definitions (extend `DefaultRolesSeeder`)

| name | slug | permission_level | applies_to_group_type_id |
|---|---|---|---|
| Bishop | `bishop` | 90 | null |
| Governor | `governor` | 70 | id of "Constituency" GroupType |

Existing role seeds (Super Admin 100, Zone Overseer 80, District Pastor 60, Cell Leader 40) untouched.

### New GroupType seed

| field | value |
|---|---|
| name | Constituency |
| slug | `constituency` |
| level | 0 |
| tracks_attendance | false |
| color | tenant default |
| icon | sensible default |

Seeded by extending the existing GroupType seeder (matching the established pattern).

### Wiring leaders

- A **Bishop**: `LeaderRole` row with `role_definition_id` = Bishop, `group_id = NULL`, `is_active = true`. One per tenant.
- A **Governor**: `LeaderRole` row with `role_definition_id` = Governor, `group_id` = a Constituency Group's id, `is_active = true`. One per Constituency.
- A **Constituency**: `Group` row with `group_type_id` = Constituency GroupType, `parent_id = NULL`.
- A tenant's existing **Cell Groups** get `parent_id` set to the appropriate Constituency. (Tenants not yet using this governance model are unaffected.)

### Future-proofing for multi-Bishop

Bishop's `group_id` is `NULL` today. If multi-Bishop arrives, add a "Diocese" GroupType, set Bishop's `group_id` to a Diocese, and parent Constituencies to Dioceses. No app contract changes; no migrations to existing data.

## Endpoint catalog

All under `/api/v1`, behind `auth:sanctum` + `InitializeLeaderScope`. Response envelope: `{ success: true, data: ... }`.

### Governor (gated `role:governor`)

| App method | Route | Returns |
|---|---|---|
| `governor.dashboard()` | `GET /governor/dashboard` | `GovernorDashboard` |
| `governor.groups()` | `GET /governor/groups` | `GovernorGroup[]` |
| `governor.groupDetail(id)` | `GET /governor/groups/{id}` | `GovernorGroupDetail` |
| `governor.members(params?)` | `GET /governor/members` | Paginated `Member[]` |
| `governor.attendance(params?)` | `GET /governor/attendance` | `GovernorAttendanceOverview` |

### Bishop (gated `role:bishop`)

| App method | Route | Returns |
|---|---|---|
| `bishop.governors()` | `GET /bishop/governors` | `BishopGovernorSummary[]` |
| `bishop.attendance(params?)` | `GET /bishop/attendance` | Attendance overview aggregated across every Constituency in the tenant |
| `bishop.members(params?)` | `GET /bishop/members` | Paginated members across every Cell Group parented to a Constituency in the tenant |
| `bishop.governorDashboard(govId)` | `GET /bishop/governors/{govId}/dashboard` | `GovernorDashboard` |
| `bishop.governorGroups(govId)` | `GET /bishop/governors/{govId}/groups` | `GovernorGroup[]` |
| `bishop.governorAttendance(govId, params?)` | `GET /bishop/governors/{govId}/attendance` | `GovernorAttendanceOverview` |
| `bishop.groupDetail(govId, groupId)` | `GET /bishop/governors/{govId}/groups/{groupId}` | `GovernorGroupDetail` |

### Response shapes (matching `lib/mock.ts`)

```
GovernorDashboard: {
  total_members: int,
  total_groups: int,
  total_leaders: int,
  sunday_attendance: int,
  midweek_attendance: int,
  groups_submitted_sunday: int,
  groups_submitted_midweek: int,
}

GovernorGroup: {
  id: int, name: string, members_count: int, leader_name: string|null,
  sunday_submitted: bool, midweek_submitted: bool,
  latest_sunday_attendance: int|null, latest_midweek_attendance: int|null,
}

GovernorGroupDetail: GovernorGroup & {
  members: { id, first_name, last_name, is_active }[]
}

GovernorAttendanceOverview: {
  series: { date: ISO, sunday: int|null, midweek: int|null }[],
  totals: { sunday: int, midweek: int },
}

BishopGovernorSummary: {
  id: int (Constituency Group id — one row per Constituency),
  constituency_name: string,
  total_members: int, total_groups: int,
  sunday_attendance: int, midweek_attendance: int,
  governor: { id (Leader id), member: { id, first_name, last_name } } | null
}
```

### Query params

- Pagination (`/governor/members`, `/bishop/members`): `page`, `per_page` (default 25).
- Date range (attendance endpoints): `from`, `to` ISO dates. Default = current week (Mon–Sun containing today).

## `ConstituencyAnalytics` service

```php
namespace App\Services\Governance;

class ConstituencyAnalytics
{
    public function dashboard(Group $constituency): array;
    public function groups(Group $constituency): array;
    public function members(Group $constituency, int $perPage = 25): LengthAwarePaginator;
    public function attendance(Group $constituency, CarbonInterval $range): array;
    public function groupDetail(Group $constituency, int $groupId): array;

    public function tenantWideAttendance(CarbonInterval $range): array;
    public function tenantWideMembers(int $perPage = 25): LengthAwarePaginator;
    public function constituencySummaries(): array;
}
```

Sunday/Midweek classification lives entirely in this service. Each query that needs the distinction selects `AttendanceSummary` rows for the relevant Cell Groups, partitions by `Carbon::parse($row->date)->isSunday()`, and picks latest within the requested window. Single point of truth — controllers do not touch this logic.

Submission flags (`sunday_submitted`, `midweek_submitted`) are computed per group as "has an `AttendanceSummary` whose date falls in the current week (Mon–Sun) and whose day-of-week matches the service slot".

## Auth and scoping

### Route registration (`routes/tenant.php`)

```php
Route::prefix('governor')->middleware('role:governor')->group(function () {
    Route::get('dashboard',     [GovernorController::class, 'dashboard']);
    Route::get('groups',        [GovernorController::class, 'groups']);
    Route::get('groups/{id}',   [GovernorController::class, 'groupDetail']);
    Route::get('members',       [GovernorController::class, 'members']);
    Route::get('attendance',    [GovernorController::class, 'attendance']);
});

Route::prefix('bishop')->middleware('role:bishop')->group(function () {
    Route::get('governors',     [BishopController::class, 'governors']);
    Route::get('attendance',    [BishopController::class, 'attendance']);
    Route::get('members',       [BishopController::class, 'members']);
    Route::get('governors/{govId}/dashboard',          [BishopController::class, 'governorDashboard']);
    Route::get('governors/{govId}/groups',             [BishopController::class, 'governorGroups']);
    Route::get('governors/{govId}/groups/{groupId}',   [BishopController::class, 'groupDetail']);
    Route::get('governors/{govId}/attendance',         [BishopController::class, 'governorAttendance']);
});
```

### `CheckRole` middleware fix

Today `CheckRole` checks `user_type` on the User model. The project has since moved to `LeaderRole`-driven roles (per `Leader::hasRole`/`hasAnyRole`). Update `CheckRole` to call `Leader::hasAnyRole($slugs)` against the leader loaded by `InitializeLeaderScope`. No existing route uses `role:` middleware today, so this fix has no regression surface.

### Resolving the authenticated Governor's Constituency

Helper in `GovernorController` (or a small trait):

```php
protected function authenticatedConstituency(Request $request): Group
{
    $role = $request->leader
        ->leaderRoles()
        ->whereHas('roleDefinition', fn($q) => $q->where('slug', 'governor'))
        ->where('is_active', true)
        ->whereNotNull('group_id')
        ->first();

    abort_if(!$role, 403, 'no constituency assigned');
    return $role->group;
}
```

A 403 with `{ success: false, message: 'no constituency assigned' }` for misconfigured Governors (active role but no `group_id`).

### Bishop drill-down validation

Helper resolving `{govId}` to a Constituency:

```php
protected function constituencyForGovernor(int $govId): Group
{
    $governor = Leader::whereHas('leaderRoles', fn($q) =>
            $q->whereHas('roleDefinition', fn($r) => $r->where('slug', 'governor'))
              ->where('is_active', true)
              ->whereNotNull('group_id'))
        ->findOrFail($govId);

    return $governor->leaderRoles
        ->firstWhere(fn($lr) => $lr->roleDefinition->slug === 'governor' && $lr->is_active)
        ->group;
}
```

`findOrFail` → 404 for invalid `govId`. Group-level scoping (`groupId`'s `parent_id` matches the resolved Constituency) → 404 if not.

### Edge cases

- **Empty tenant** (no Constituencies): `bishop.governors()` → `{ success: true, data: [] }`. Dashboards return zeros, not errors.
- **Governor calling `groups/{id}` for a group outside their Constituency**: 404.
- **Bishop calling `governors/{govId}/...` for a non-governor leader**: 404.

## Testing

### Service unit tests — `tests/Unit/Services/Governance/ConstituencyAnalyticsTest.php`

Database-backed (`RefreshDatabase`). Per public method, build a fixture: 1 Constituency + 3 Cell Groups (5–10 members each) + `AttendanceSummary` rows on known Sunday and weekday dates. Assert:

- Aggregates: `total_members`, `total_groups`, `total_leaders`, `sunday_attendance`, `midweek_attendance`, submission counts.
- `latest_sunday_attendance` / `latest_midweek_attendance` per group.
- Day-of-week classification: Sunday-dated → Sunday; Wednesday-dated → midweek.
- Same-day duplicate handled deterministically (latest by created_at).
- Date-range filtering on `attendance()`.
- Pagination shape on `members()`.
- Tenant-wide variants aggregate across multiple Constituencies.

### Controller feature tests

- `tests/Feature/Api/GovernorControllerTest.php`
- `tests/Feature/Api/BishopControllerTest.php`

Per controller, per endpoint:

- **Auth/role gating:** Unauthenticated → 401. Wrong role → 403. Right role → 200.
- **Response envelope:** `{ success: true, data: ... }`. `data` shape contains the documented keys.
- **Scoping:** Governor `groups/{id}` outside their Constituency → 404. Bishop `governors/{govId}/...` with non-governor → 404.
- **Empty-state:** Bishop with zero Constituencies → `data: []`.
- **Misconfigured Governor** (active role, null `group_id`) → 403 with the documented message.
- **Pagination:** Honored on the two paginated endpoints.

Tests do not mock `ConstituencyAnalytics` — they hit the real DB through it. Faster than mocking and catches drift between service expectations and controller wiring.

### Out of scope for tests

Performance/load tests, large-dataset stress tests. Add caching and benchmark only when measured need exists.

## Files touched

**New:**
- `app/Http/Controllers/Api/GovernorController.php`
- `app/Http/Controllers/Api/BishopController.php`
- `app/Services/Governance/ConstituencyAnalytics.php`
- `tests/Unit/Services/Governance/ConstituencyAnalyticsTest.php`
- `tests/Feature/Api/GovernorControllerTest.php`
- `tests/Feature/Api/BishopControllerTest.php`

**Edited:**
- `routes/tenant.php` — register the two route groups.
- `app/Http/Middleware/CheckRole.php` — delegate to `Leader::hasAnyRole`.
- `database/seeders/DefaultRolesSeeder.php` — add `bishop`, `governor` rows.
- The existing GroupType seeder — add `Constituency` row.

**Untouched:** existing controllers (Dashboard, Member, Group, Attendance), existing roles, existing migrations.
