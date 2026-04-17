# Attendance Summary CSV Import

## Problem

Antwerp (and other tenants) hold historical bacenta attendance data in spreadsheets that predates their use of Flock. Each row is `Date | Bacenta Name | Total Attendance`. Admins need a way to load these records into the app so historical attendance is visible alongside newer in-app submissions, without re-entering ~735 rows by hand.

## Goals

- Per-tenant admins can import historical attendance summaries from a CSV.
- Rows are mapped to existing bacentas (`Group` records) by name.
- Existing summaries for the same `(group, date)` are fully overwritten.
- Unmatched rows are reported so the admin can reconcile and re-run.
- The import reuses Filament's existing importer infrastructure already proven by `MemberImporter`.

## Non-goals

- xlsx upload support (user exports to CSV).
- Fuzzy name matching or interactive mapping UI.
- Auto-creating missing bacentas.
- Populating `visitor_count`, `first_timer_count`, `notes`, or individual `Attendance` child records (the source sheet only carries totals).

## Decisions (from brainstorming)

| Decision | Chosen |
|---|---|
| Scope | Reusable Filament admin feature (any tenant) |
| Name matching | Auto-match only; report unmatched |
| Duplicate handling | Full overwrite of the existing summary |
| File format | CSV only |
| `submitted_by_leader_id` | Null for imported rows |

## Architecture

One new class, one edit:

- **New:** `app/Filament/Imports/AttendanceSummaryImporter.php`
  Extends `Filament\Actions\Imports\Importer` (same base class and pattern as `app/Filament/Imports/MemberImporter.php`).
- **Edit:** `app/Filament/Resources/AttendanceSummaryResource/Pages/ListAttendanceSummaries.php`
  Add `Filament\Actions\ImportAction::make(AttendanceSummaryImporter::class)` to `getHeaderActions()`.

The existing `App\Filament\Resources\ImportResource` ("Import History" page) automatically surfaces runs of the new importer. Failed rows appear in its `FailedRowsRelationManager` with no further changes.

Groups are already tenant-scoped via `stancl/tenancy`, so name lookups in the admin panel are automatically constrained to the current tenant.

## Column mapping

CSV headers map as follows (matches the Antwerp sheet verbatim):

| CSV header | Maps to | Rules |
|---|---|---|
| `Date` | `date` | `required`, `date` |
| `Name` | virtual column → resolved to `group_id` | `required`; must match an existing `Group` after normalization |
| `Total Attendance` | `total_attendance` | `required`, `integer`, `min:0` |

`Name` is declared via `ImportColumn::make('group')->fillRecordUsing(fn () => null)` (same trick `MemberImporter` uses for its group column), with the real resolution happening in `beforeSave()`.

## Name matching

A single normalization function is applied to both sheet names and candidate `Group->name` values before comparison:

1. Trim leading/trailing whitespace.
2. Collapse internal whitespace runs to a single space.
3. Lowercase (Unicode-safe via `mb_strtolower`).
4. Normalize curly quotes and apostrophes to straight ASCII (`'` → `'`, `"` → `"`).

Per normalized name, the importer queries `Group` for all rows whose normalized name matches, caches the result (a list of 0, 1, or 2+ matches) on `$groupCache`, and reuses the cached list for later rows with the same name. Unmatched names accumulate in `$unmatchedGroups` and are surfaced in the completion notification.

Match count drives the outcome: 0 → unmatched (fail + report); 1 → resolve; 2+ → fail the row with reason `"Ambiguous bacenta name: <name>"` rather than silently picking one. Caching the list (not a single Group) is what lets us detect ambiguity cheaply on repeat encounters.

## Per-row data flow

1. Validate mapped fields (`date`, `total_attendance`) via rules.
2. Normalize `Name`; look up `Group` via cache.
3. On miss → push into `$unmatchedGroups`; fail the row with reason `"Bacenta not found: <name>"`.
4. On ambiguous match → fail the row with reason `"Ambiguous bacenta name: <name>"`.
5. `resolveRecord()` returns `AttendanceSummary::firstOrNew(['group_id' => $groupId, 'date' => $date])`.
6. Inside a DB transaction:
   - If record exists: delete its child `attendances` and `nonMemberAttendances` rows (make overwrite clean — no dangling children whose totals no longer match the summary).
   - Apply `total_attendance` from the CSV; reset `visitor_count`, `first_timer_count`, `notes`, `image`, `submitted_by_leader_id` to null/0.
   - Save.

## Error handling

Row-level failures are written to `FailedImportRow` with an explanatory reason. Other rows in the batch continue.

| Condition | Reason |
|---|---|
| Missing/unparseable date | `"Invalid date"` (Laravel rule message) |
| Missing `Name` | `"Bacenta name required"` (Laravel rule message) |
| Missing/non-numeric total | Rule message |
| Negative total | Rule message |
| Bacenta not found | `"Bacenta not found: <name>"` |
| Ambiguous bacenta | `"Ambiguous bacenta name: <name>"` |

Batch-level:

- Import runs as a queued job (Filament default) — chunked 100 rows per chunk.
- A DB transaction wraps each row's mutate-and-save so a mid-row failure leaves no half-deleted children.

## Edge cases

- Empty trailing rows in the CSV are skipped by Filament's parser.
- Excel numeric serial dates (e.g. `45907`) do not arrive — CSV export renders them as `YYYY-MM-DD` or locale-formatted strings, which Carbon parses.
- Trailing UTF-8 BOM on headers is handled by Filament's CSV parser.
- Duplicate rows within the same CSV for the same `(bacenta, date)`: last one wins (processed in order). Acceptable — the user owns the sheet.
- Dates are stored on a `date` column, not `datetime`, so no timezone math.

## Completion notification

Shape mirrors `MemberImporter::getCompletedNotificationBody`:

> `<N> rows imported. <M> failed. Unmatched bacentas: <comma-separated names>`

## Testing

Feature tests (`tests/Feature/Filament/AttendanceSummaryImporterTest.php`) using `RefreshDatabase`:

1. **Happy path** — CSV with 3 rows referencing 3 existing bacentas creates 3 `AttendanceSummary` rows with correct `group_id`, `date`, `total_attendance`; `submitted_by_leader_id` is null.
2. **Overwrite** — Pre-seed an `AttendanceSummary` with `visitor_count=5`, `notes="original"`, and two child `Attendance` rows. Import one CSV row for the same `(group, date)` with a new total. Assert: `total_attendance` updated, `visitor_count`/`notes` reset, child `attendances` deleted.
3. **Failure modes** — CSV contains (a) unknown bacenta, (b) unparseable date, (c) negative total. Assert: three `FailedImportRow` rows with the expected reasons; no corresponding `AttendanceSummary` rows created; other valid rows in the file still succeed.

Unit test (`tests/Unit/AttendanceSummaryImporter/NormalizeNameTest.php`):

- Whitespace variants, case variants, curly-quote variants all normalize to the same value.

No new dependencies — existing PHPUnit + Laravel test stack.

## Out of scope / future work

- xlsx upload (adds PhpSpreadsheet dependency; user can export to CSV).
- Preview / mapping UI before commit (only worth it if fuzzy matching is added).
- Importing `visitor_count` / `first_timer_count` / notes (source sheet doesn't carry them).
