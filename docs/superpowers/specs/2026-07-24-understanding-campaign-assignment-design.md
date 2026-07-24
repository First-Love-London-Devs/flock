# Understanding Campaign: rep assignment flow

**Date:** 2026-07-24
**Repos:** `flock/server` (main) and `flock/app` (master)
**Builds on:** `2026-06-23-understanding-campaign-form-design.md`

## Why this exists

The public `/welcome` form already captures first-timers and re-dedications
into the per-tenant `understanding_campaigns` table. Today the only place to
see those records or assign a person to a bacenta is the Filament admin on
desktop (`UnderstandingCampaignResource`). Nobody in the field can do it.

This adds a mobile flow so a designated rep, scoped to one gathering service,
can work through their incoming first-timers and converts and drop each one
into a bacenta from their phone.

## Scope

**In:** view the campaign records for my gathering service, split into
still-to-assign and already-assigned; open a person; assign or re-assign them
to a bacenta under my gathering service.

**Out, on purpose (all clean later additions):**
- follow-up: calls, WhatsApp, contact logging, nurture status
- promoting a record into a real `Member` with group membership and attendance
- editing the person's captured details
- any change to the `/welcome` form or the `understanding_campaigns` schema

"Assign" means exactly what the desktop admin does now: set
`allocated_group_id`. Nothing is created; it is a pointer the bacenta leader
picks up from.

## The role

A new `RoleDefinition`, seeded per tenant:
- `name`: "Understanding Campaign"
- `slug`: `understanding-campaign`
- `applies_to_group_type_id`: **null**. Group types are tenant-defined
  (`DefaultRolesSeeder` ships Zone / District / Constituency / Cell; a given
  church renames these to Gathering Service / Stream / Bacenta etc.), so there
  is no fixed "gathering service" type id to point at. Null matches how Bishop
  and Admin are already seeded and lets the admin attach the role to whichever
  group is that tenant's gathering service.
- `permission_level`: **40**, the field-worker tier already used by Cell Leader
  and Ministry Leader, well below Governor (70) and admin roles.

Seeding is in two places, both idempotent:
- add the row to `DefaultRolesSeeder` so every new tenant gets it;
- a tenant data migration (`database/migrations/tenant/`, `role_definitions` is
  a tenant table) inserts it for existing tenants via `firstOrCreate` on the
  slug, run with `tenants:migrate --force`.

A leader is granted the role by a `LeaderRole` row (`role_definition_id` +
`group_id` = the gathering-service group the admin picks). This is the same
mechanism every other role uses, so it is assignable from the existing admin
with no new tooling, and the scoping below keys off that `group_id` regardless
of what the tenant calls that level.

## Scoping rule (the one thing to get right)

A rep holds the role on a gathering-service group G. Their world is G's whole
subtree:

- **Records they see:** `understanding_campaigns` where `stream_id` is in
  `G->allGroupIds()` (G plus every descendant). `allGroupIds()` already exists
  on `Group` and returns descendant ids plus self, so this works whether the
  form's "stream" is the gathering service itself or a level beneath it.
- **Bacentas they can assign to:** groups whose `groupType.tracks_attendance`
  is true AND whose id is in `G->allGroupIds()`. `tracks_attendance` is the
  existing marker for a bacenta, and the desktop resource already filters
  allocation targets this exact way.

**The endpoints resolve G from the caller's own Understanding Campaign role,
not from `getAccessibleGroupIds()`.** `getAccessibleGroupIds()` merges every
active role a leader holds, which would over-scope a rep who also holds another
role. Instead each endpoint finds the caller's active `understanding-campaign`
`LeaderRole`, reads its `group_id`, and scopes to that group's subtree. A
leader with the role on two gathering services is out of scope for v1; if it
ever matters, the active-role id the app already stores can disambiguate.

## Server (flock/server, tenant API)

All under the existing `prefix('api/v1')` group in `routes/tenant.php`, behind
`auth:sanctum` + `InitializeLeaderScope`, and additionally gated with
`CheckRole:understanding-campaign` so only reps reach them.

New `UnderstandingCampaignController`:

- `GET /api/v1/understanding-campaigns`
  Returns the caller's scoped records, newest `attended_on` first, each with
  id, first/last name, `first_time`, `re_dedicating`, `attended_on`,
  `who_invited`, `phone_number`, `stream` (id + name), and `allocated_group`
  (id + name, or null). Optional `?status=unassigned|assigned` filter, mirroring
  the desktop resource's unallocated filter.

- `GET /api/v1/understanding-campaigns/assignable-groups`
  Returns the bacentas in the caller's subtree (id + name), for the assign
  picker.

- `PATCH /api/v1/understanding-campaigns/{id}/assign`
  Body `{ "allocated_group_id": <id|null> }`. Validates the record is in the
  caller's scope AND the target group is one of their assignable bacentas
  (else 403/422). Null clears the assignment. Returns the updated record.

A single **`FormRequest`** does the scope + target validation so the controller
stays thin. A **seeder/migration** creates the role definition idempotently for
each tenant (tenant migration, run with `tenants:migrate --force`).

No change to `understanding_campaigns`, its model, or the `/welcome` form.

## App (flock/app, Expo Router)

Routing keys on `roleDefinition.slug` in `app/roles.tsx`. Add a case:
`slug === 'understanding-campaign'` routes into a new group, e.g.
`app/(campaign)/`.

Two screens:

- **List** (`(campaign)/index`): a header with the gathering-service name, a
  segmented control "To assign" / "Assigned", and a list of people. Each row:
  name, a first-timer or re-dedication chip, date attended, who invited. Pull
  to refresh. The "To assign" tab is the default and the point of the screen.

- **Detail / assign** (`(campaign)/[id]`): the person's captured details and
  phone, and an "Assign to bacenta" action opening a picker of the assignable
  bacentas. On confirm it PATCHes and returns to the list, which reflects the
  move. If already assigned, the same control re-assigns.

Data access goes through the existing `lib/api.ts` fetch wrapper (bearer token
already handled). Follow the shapes and patterns the governor/bishop flows
already use for lists and detail screens rather than inventing new ones.

## Data flow

1. Rep signs in, picks the Understanding Campaign role → `(campaign)/index`.
2. List screen calls `GET /understanding-campaigns?status=unassigned`.
3. Rep opens a person → detail screen; taps assign → picker calls
   `GET /understanding-campaigns/assignable-groups`.
4. Confirm → `PATCH /understanding-campaigns/{id}/assign` → back to list,
   person now under "Assigned".

## Error handling

- Caller lacks the role: `CheckRole` returns 403 before the controller runs.
- Record or target group outside the caller's subtree: 403/422 from the
  FormRequest, so a rep can never assign someone else's person or assign into
  another gathering service.
- Empty states: "No one to assign right now" and "No bacentas set up for your
  gathering service yet" render explicitly rather than as blank lists.
- Network failure on the list or the assign: inline retry, no silent failure.

## Testing

- **Server feature tests** (tenant context, `RefreshDatabase` is already on the
  base `TestCase`): a rep sees only their subtree's records; the unassigned
  filter works; assigning to an in-scope bacenta succeeds and sets the field;
  assigning a record outside the subtree is forbidden; assigning to a
  non-`tracks_attendance` or out-of-subtree group is rejected; null clears it;
  a leader without the role gets 403.
- **App:** `npx tsc --noEmit` against the repo's existing baseline (not clean on
  master; own only the touched files). Manual pass on a tenant: role appears,
  list scopes correctly, assign moves a person between tabs.

## Deploy order

Server first (it is the API and owns the role seed), `tenants:migrate --force`
to seed the role into each tenant, confirm the endpoints, then OTA the app.
The app hits the remote API, so nothing is testable on device until the server
is deployed.

## Notable gotchas carried in

- Gate behaviour on the GroupType / role slug, not on assumptions about role
  naming; `CheckRole` already matches on `roleDefinition.slug`.
- Tenant route names do not resolve via `route()`; use paths in tenant context.
- First-timer vs re-dedication is the `first_time` / `re_dedicating` boolean
  pair on the record, not a member type.
