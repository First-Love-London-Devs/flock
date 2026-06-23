# Understanding Campaign — First-Timer / Convert Capture Form

**Date:** 2026-06-23
**Project:** Flock (server) — multi-tenant Laravel
**Primary tenant:** go-church (`gochurch.church-stack.com`)
**Status:** Approved design, pending spec sign-off

## Overview

A public, bilingual (English / Dutch) web form on a church's own Flock domain
where first-timers and people re-dedicating their lives ("converts") enter their
details themselves. Submissions land in the church's Filament admin under a new
**Understanding Campaign** section, where staff review each one and **allocate it
to a Bacenta**.

Built tenant-generically (route + table + admin resource live in the shared
codebase; all data is per-tenant). go-church is the first/only user for now; the
form's labels are bilingual EN/NL to suit them.

## Architecture

Mirrors how Flock already works — no new patterns introduced:

- **Public form:** a Blade page served from the tenant web routes
  (`routes/tenant.php`, the `web` + `InitializeTenancyByDomain` group), exactly
  like the existing `/`, and like the central `welcome`/`support`/`privacy`
  Blade pages. No authentication.
- **Persistence:** a new per-tenant table `understanding_campaigns` (in
  `database/migrations/tenant/`) + an `UnderstandingCampaign` Eloquent model.
- **Admin:** a Filament resource (`app/Filament/Resources/UnderstandingCampaignResource.php`),
  auto-discovered into the existing tenant **admin** panel (`AdminPanelProvider`,
  path `admin`) → appears at `gochurch.church-stack.com/admin`.

## Data model

New tenant table `understanding_campaigns`:

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `attended_on` | date | "Date" on the form; defaults to today |
| `first_name` | string | required |
| `last_name` | string | required (Surname / Achternaam) |
| `street_name` | string, nullable | Straatnaam |
| `postal_code` | string, nullable | Postcode |
| `phone_number` | string | required |
| `re_dedicating` | boolean, default false | "Re-dedicating your life to Christ?" |
| `first_time` | boolean, default false | "First time at this church?" |
| `who_invited` | string, nullable | free text |
| `allocated_group_id` | FK → `groups.id`, nullable, `nullOnDelete` | the allocated Bacenta; set by staff in admin |
| `created_at` / `updated_at` | timestamps | |

`UnderstandingCampaign` model: the above as `$fillable` (except `id`/timestamps),
`attended_on` + booleans cast appropriately, and an `allocatedGroup()` belongsTo
relation to `Group`.

## Public form

- **Routes** (in `routes/tenant.php`, `web` + `InitializeTenancyByDomain` group, no auth):
  - `GET /welcome` → renders the form.
  - `POST /welcome` → validates + stores an `UnderstandingCampaign`, redirects back with a bilingual success flash.
- **Controller:** `App\Http\Controllers\Web\WelcomeFormController` with `show()` and `store()`. Tenancy is already initialised by the route group, so the model writes to the tenant DB.
- **View:** a new Blade view (e.g. `resources/views/welcome-form.blade.php` — NOT the existing central `welcome.blade.php`). Single-column, mobile-friendly, lightly styled (Tailwind is available; otherwise minimal inline/CSS matching the existing public pages). Each label shows EN / NL together, e.g. `First Name / Voornaam`.
- **Fields & validation:**
  - `attended_on` — required date, defaults to today.
  - `first_name`, `last_name`, `phone_number` — required strings.
  - `street_name`, `postal_code`, `who_invited` — nullable strings.
  - `re_dedicating`, `first_time` — required Yes/No (radio), stored as boolean.
- **Submit behaviour:** on success show a friendly bilingual thank-you message (flash on the same page, form cleared). On validation error, redisplay with messages and old input. CSRF via the standard `web` middleware.
- **Classification:** `first_time = true` ⇒ first-timer; `re_dedicating = true` ⇒ convert/re-dedication. Both are independent booleans (a person can be either, both, or neither).

## Admin side (Understanding Campaign)

- **`UnderstandingCampaignResource`** in the tenant admin panel, navigation label **"Understanding Campaign"**.
- **List page:** columns — date, full name, phone, first-timer (badge), re-dedicating (badge), who invited, allocated Bacenta. Sortable by date (newest first), searchable by name/phone. A filter for "unallocated" is nice-to-have.
- **Edit/View:** all submitted fields read-only-ish (staff don't normally edit a person's own answers), plus the editable **Allocated Bacenta** field.
- **Allocated Bacenta field:** a `Select` of Bacenta-type `Group`s — Groups whose `GroupType` is the Bacenta type (identified by `GroupType` slug, per the existing slug-based convention; exact slug confirmed against go-church's group types during implementation). Searchable, nullable. Saving sets `allocated_group_id`.

## Out of scope (v1)

- No email/push notification on submission (staff check the admin list). Confirmed with owner.
- No authentication on the public form (it is intentionally public).
- No automatic creation of a `Member`/`NonMember` from a submission, and no
  attendance linkage — a submission is a standalone record staff triage. (Could
  be a future enhancement: "convert this submission into a NonMember/Member".)
- No per-tenant language configuration — labels are hardcoded bilingual EN/NL for
  go-church. Revisit if another tenant adopts the form.

## Testing

- **Feature test (public form):** within a tenant context, `POST /welcome` with
  valid data creates one `understanding_campaigns` row with the right values;
  invalid data (missing required field) returns validation errors and creates
  nothing. `GET /welcome` returns 200 and renders the form.
- **Feature/unit test (allocation):** an `UnderstandingCampaign` can be allocated
  to a Bacenta `Group` (`allocated_group_id` set, `allocatedGroup` relation
  resolves).
- Follow Flock's existing tenant-aware test setup (a tenant is created/initialised
  for the test; reuse the project's existing test helpers/patterns).
- Validate with the project's standard commands before shipping.

## Deployment notes

- New tenant migration must run for every tenant (`tenants:migrate --force` after
  deploy — Flock is multi-tenant; see project deploy notes). Server default branch
  is `main`, Forge Quick Deploy.
