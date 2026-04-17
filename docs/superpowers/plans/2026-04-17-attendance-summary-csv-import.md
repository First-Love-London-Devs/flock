# Attendance Summary CSV Import — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Admins can import historical bacenta attendance summaries from a CSV via a button on the Attendance Summaries list page, auto-matching rows to existing `Group` records by name and fully overwriting any existing summary for the same `(group, date)`.

**Architecture:** Filament-native `Importer` subclass that follows the exact pattern of the existing `MemberImporter`. One new class (`AttendanceSummaryImporter`) plus one header action wired into `ListAttendanceSummaries`. The existing `ImportResource` surfaces history and failed rows automatically. Tenant scoping is handled by `stancl/tenancy` — no extra filtering needed.

**Tech Stack:** Laravel 10, Filament v3, stancl/tenancy v3, PHPUnit 10.

**Spec:** `docs/superpowers/specs/2026-04-17-attendance-summary-csv-import-design.md`

---

## Background the engineer needs

Before starting:

- Read the spec listed above. It documents the decisions: CSV-only, auto-match (no fuzzy matching), full overwrite semantics, `submitted_by_leader_id` is null.
- Read `app/Filament/Imports/MemberImporter.php`. This is the reference implementation the new importer mirrors.
- The `attendance_summaries` table has `UNIQUE(group_id, date)` — `firstOrNew` on that pair is the right primitive.
- On overwrite, the child tables `attendances` and `non_member_attendances` reference `attendance_summaries` via `attendance_summary_id`. Those child rows must be deleted when a summary is overwritten, otherwise they're orphaned numbers attached to a stale parent.
- Filament `Importer::__invoke(array $data)` is the public entry point that runs one row's full lifecycle (resolve → validate → fill → save). Tests drive it by constructing the importer and calling `$importer($rowData)`.
- Multi-tenancy: this importer lives in the *admin* panel, which is per-tenant. The central panel is untouched. `Group::where('name', ...)` automatically resolves to the current tenant's Groups because the tenancy middleware has already switched the DB connection.

---

## File Structure

**New:**
- `app/Filament/Imports/AttendanceSummaryImporter.php` — the importer; owns column definitions, name resolution cache, overwrite behavior, and completion notification.
- `tests/Unit/AttendanceSummaryImporter/NormalizeNameTest.php` — pure-function unit test for name normalization.

**Modified:**
- `app/Filament/Resources/AttendanceSummaryResource/Pages/ListAttendanceSummaries.php` — adds a header `ImportAction` pointing at the new importer.

**Deferred (noted in spec):** Filament lifecycle integration tests require a tenant-scoped PHPUnit harness that does not yet exist in this codebase. Those tests are not part of this plan; coverage for the behavior comes from the unit test plus a detailed manual smoke test at the end (Task 5). If a tenant test harness is added later, the spec's three feature test cases can be backfilled.

---

## Task 1: Name normalizer (pure function + unit test)

**Why first:** The normalizer is a pure function with no framework dependencies. TDD it in isolation before the importer uses it.

**Files:**
- Create: `app/Filament/Imports/AttendanceSummaryImporter.php` (just the normalizer method for now)
- Create: `tests/Unit/AttendanceSummaryImporter/NormalizeNameTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/AttendanceSummaryImporter/NormalizeNameTest.php`:

```php
<?php

namespace Tests\Unit\AttendanceSummaryImporter;

use App\Filament\Imports\AttendanceSummaryImporter;
use Tests\TestCase;

class NormalizeNameTest extends TestCase
{
    /** @dataProvider equivalentNamesProvider */
    public function test_equivalent_names_normalize_to_the_same_value(string $a, string $b): void
    {
        $this->assertSame(
            AttendanceSummaryImporter::normalizeName($a),
            AttendanceSummaryImporter::normalizeName($b),
        );
    }

    public static function equivalentNamesProvider(): array
    {
        return [
            'trailing whitespace' => ['Fruitfulness 1', 'Fruitfulness 1   '],
            'leading whitespace' => ['Fruitfulness 1', '   Fruitfulness 1'],
            'collapsed whitespace' => ['Fruitfulness 1', 'Fruitfulness   1'],
            'case' => ['Fruitfulness 1', 'fruitfulness 1'],
            'mixed case' => ['Fruitfulness 1', 'FRUITFULNESS 1'],
            'curly apostrophe' => ["God's Presence 1", "God\u{2019}s Presence 1"],
            'curly quotes' => ['Name "quoted" 1', "Name \u{201C}quoted\u{201D} 1"],
        ];
    }

    public function test_different_names_normalize_differently(): void
    {
        $this->assertNotSame(
            AttendanceSummaryImporter::normalizeName('Fruitfulness 1'),
            AttendanceSummaryImporter::normalizeName('Fruitfulness 2'),
        );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit --filter NormalizeNameTest`
Expected: FAIL with `Class "App\Filament\Imports\AttendanceSummaryImporter" not found`.

- [ ] **Step 3: Create the importer file with only the normalizer method**

Create `app/Filament/Imports/AttendanceSummaryImporter.php`:

```php
<?php

namespace App\Filament\Imports;

use App\Models\AttendanceSummary;
use Filament\Actions\Imports\Importer;

class AttendanceSummaryImporter extends Importer
{
    protected static ?string $model = AttendanceSummary::class;

    public static function normalizeName(string $name): string
    {
        $name = str_replace(
            ["\u{2018}", "\u{2019}", "\u{201C}", "\u{201D}"],
            ["'", "'", '"', '"'],
            $name,
        );
        $name = trim($name);
        $name = preg_replace('/\s+/u', ' ', $name);
        return mb_strtolower($name, 'UTF-8');
    }

    public static function getColumns(): array
    {
        return [];
    }

    public function resolveRecord(): ?AttendanceSummary
    {
        return new AttendanceSummary();
    }
}
```

The empty `getColumns()` and placeholder `resolveRecord()` are scaffolding so PHP can load the class — they get filled in by Task 2.

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit --filter NormalizeNameTest`
Expected: PASS — all 8 cases green.

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Imports/AttendanceSummaryImporter.php tests/Unit/AttendanceSummaryImporter/
git commit -m "Add AttendanceSummaryImporter with name normalizer"
```

---

## Task 2: Column definitions + group resolution

**Files:**
- Modify: `app/Filament/Imports/AttendanceSummaryImporter.php`

- [ ] **Step 1: Replace the class body with the full columns + resolution skeleton**

Replace the entire file contents with:

```php
<?php

namespace App\Filament\Imports;

use App\Models\AttendanceSummary;
use App\Models\Group;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Validation\ValidationException;

class AttendanceSummaryImporter extends Importer
{
    protected static ?string $model = AttendanceSummary::class;

    /** @var array<string, array<int, Group>> */
    protected static array $groupCache = [];

    /** @var array<string, true> */
    protected static array $unmatchedGroups = [];

    /** @var array<string, true> */
    protected static array $ambiguousGroups = [];

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('date')
                ->requiredMapping()
                ->rules(['required', 'date'])
                ->example('2025-10-05'),
            ImportColumn::make('group')
                ->label('Bacenta Name')
                ->requiredMapping()
                ->rules(['required', 'string'])
                ->fillRecordUsing(fn () => null)
                ->example('Fruitfulness 1'),
            ImportColumn::make('total_attendance')
                ->requiredMapping()
                ->rules(['required', 'integer', 'min:0'])
                ->example('40'),
        ];
    }

    public function resolveRecord(): ?AttendanceSummary
    {
        $groupName = trim((string) ($this->data['group'] ?? ''));
        $date = $this->data['date'] ?? null;

        if ($groupName === '' || !$date) {
            return null;
        }

        $group = static::resolveGroup($groupName);

        return AttendanceSummary::firstOrNew([
            'group_id' => $group->id,
            'date' => $date,
        ]);
    }

    /**
     * Looks up a Group by normalized name. Throws ValidationException on
     * missing or ambiguous matches so Filament records a specific reason on
     * the failed_import_rows row.
     */
    protected static function resolveGroup(string $rawName): Group
    {
        $key = static::normalizeName($rawName);

        if (!isset(static::$groupCache[$key])) {
            static::$groupCache[$key] = Group::all()
                ->filter(fn (Group $g) => static::normalizeName($g->name) === $key)
                ->values()
                ->all();
        }

        $matches = static::$groupCache[$key];

        if (count($matches) === 0) {
            static::$unmatchedGroups[$rawName] = true;
            throw ValidationException::withMessages([
                'group' => "Bacenta not found: {$rawName}",
            ]);
        }

        if (count($matches) > 1) {
            static::$ambiguousGroups[$rawName] = true;
            throw ValidationException::withMessages([
                'group' => "Ambiguous bacenta name: {$rawName}",
            ]);
        }

        return $matches[0];
    }

    public static function normalizeName(string $name): string
    {
        $name = str_replace(
            ["\u{2018}", "\u{2019}", "\u{201C}", "\u{201D}"],
            ["'", "'", '"', '"'],
            $name,
        );
        $name = trim($name);
        $name = preg_replace('/\s+/u', ' ', $name);
        return mb_strtolower($name, 'UTF-8');
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = number_format($import->successful_rows) . ' ' . str('row')->plural($import->successful_rows) . ' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' failed.';
        }

        if (!empty(static::$unmatchedGroups)) {
            $names = implode(', ', array_keys(static::$unmatchedGroups));
            $body .= " Unmatched bacentas: {$names}.";
        }

        if (!empty(static::$ambiguousGroups)) {
            $names = implode(', ', array_keys(static::$ambiguousGroups));
            $body .= " Ambiguous bacenta names: {$names}.";
        }

        return $body;
    }
}
```

**Notes on the implementation:**

- `Group::all()->filter(...)` loads all groups once per tenant per import. Groups per tenant are small (dozens, not thousands), and the cache reuses the result for repeat encounters. Cheaper than adding a denormalized `name_normalized` column just for this feature.
- `fillRecordUsing(fn () => null)` on the `group` column tells Filament not to write this virtual column onto the model. Same trick `MemberImporter` uses.
- `resolveRecord()` returning `null` causes Filament to skip the row as failed.

- [ ] **Step 2: Re-run the normalizer unit test to make sure nothing regressed**

Run: `./vendor/bin/phpunit --filter NormalizeNameTest`
Expected: PASS.

- [ ] **Step 3: Commit**

```bash
git add app/Filament/Imports/AttendanceSummaryImporter.php
git commit -m "Define columns and group resolution in AttendanceSummaryImporter"
```

---

## Task 3: Overwrite behavior

When a summary already exists for `(group_id, date)`, the importer must:

1. Delete its child `attendances` and `non_member_attendances` rows.
2. Reset `visitor_count`, `first_timer_count`, `notes`, `image`, `submitted_by_leader_id` to their defaults/null.
3. Let Filament fill `total_attendance` from the CSV via the column mapping.
4. Save.

All four steps must happen in a single DB transaction so a mid-way failure leaves no half-deleted children.

**Files:**
- Modify: `app/Filament/Imports/AttendanceSummaryImporter.php`

- [ ] **Step 1: Add `beforeSave` hook that handles overwrite cleanup**

Add the following method inside the `AttendanceSummaryImporter` class, after `resolveRecord()`:

```php
public function beforeSave(): void
{
    /** @var AttendanceSummary $record */
    $record = $this->record;

    if ($record->exists) {
        $record->attendances()->delete();
        $record->nonMemberAttendances()->delete();

        $record->visitor_count = 0;
        $record->first_timer_count = 0;
        $record->notes = null;
        $record->image = null;
        $record->submitted_by_leader_id = null;
    }
}
```

Note: We don't wrap this in a `DB::transaction` ourselves — Filament's importer job already wraps the `__invoke` call per row in a transaction. If you confirm that's not the case in your Filament version (inspect `vendor/filament/actions/src/Imports/Jobs/ImportCsv.php`), wrap the method body in `DB::transaction(function () use ($record) { ... });` explicitly.

- [ ] **Step 2: Verify Filament wraps per-row in a transaction**

Run: `grep -n "DB::transaction\|transaction(" vendor/filament/actions/src/Imports/Jobs/ImportCsv.php`
Expected: a line showing each row processed inside `DB::transaction(...)`.

If not found, update `beforeSave` to:

```php
public function beforeSave(): void
{
    /** @var AttendanceSummary $record */
    $record = $this->record;

    if (!$record->exists) {
        return;
    }

    \DB::transaction(function () use ($record) {
        $record->attendances()->delete();
        $record->nonMemberAttendances()->delete();

        $record->visitor_count = 0;
        $record->first_timer_count = 0;
        $record->notes = null;
        $record->image = null;
        $record->submitted_by_leader_id = null;
    });
}
```

- [ ] **Step 3: Commit**

```bash
git add app/Filament/Imports/AttendanceSummaryImporter.php
git commit -m "Clean child rows and reset fields on attendance summary overwrite"
```

---

## Task 4: Wire the ImportAction into the list page

**Files:**
- Modify: `app/Filament/Resources/AttendanceSummaryResource/Pages/ListAttendanceSummaries.php`

- [ ] **Step 1: Replace the page file with the version that has a header action**

Replace the contents of `app/Filament/Resources/AttendanceSummaryResource/Pages/ListAttendanceSummaries.php` with:

```php
<?php

namespace App\Filament\Resources\AttendanceSummaryResource\Pages;

use App\Filament\Imports\AttendanceSummaryImporter;
use App\Filament\Resources\AttendanceSummaryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAttendanceSummaries extends ListRecords
{
    protected static string $resource = AttendanceSummaryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ImportAction::make()
                ->importer(AttendanceSummaryImporter::class)
                ->label('Import CSV'),
        ];
    }
}
```

- [ ] **Step 2: Confirm the class loads**

Run: `php artisan route:list --path=admin/attendance-summaries 2>&1 | head -5`
Expected: the route listing prints without syntax errors. (If your environment doesn't have the route listing working locally due to tenancy, open the page in the browser instead — see Task 5.)

- [ ] **Step 3: Commit**

```bash
git add app/Filament/Resources/AttendanceSummaryResource/Pages/ListAttendanceSummaries.php
git commit -m "Add Import CSV header action to attendance summaries list"
```

---

## Task 5: Manual smoke test

No automated feature tests — the codebase doesn't have a tenant-scoped PHPUnit harness (see spec's deferred-tests note). This task walks through the same scenarios the spec calls out for feature tests, but executed by hand against a local tenant.

**Preparation:**

- [ ] **Step 1: Start the app and log into the admin panel of a test tenant**

Run: `php artisan serve`. Check `.env`'s `QUEUE_CONNECTION`:
- If `sync`, no worker needed.
- If `database` or `redis`, also run `php artisan queue:work` in a second terminal.

Visit the admin panel for a test tenant. Confirm the "Attendance" → "Attendance Summaries" page loads and the new **Import CSV** button is visible in the header.

- [ ] **Step 2: Seed three test bacentas for the tenant**

In `php artisan tinker`:

```php
use App\Models\Group;
use App\Models\GroupType;

$type = GroupType::firstOrCreate(['name' => 'Bacenta']);
Group::create(['name' => 'Smoke Test Alpha', 'group_type_id' => $type->id, 'is_active' => true]);
Group::create(['name' => 'Smoke Test Beta', 'group_type_id' => $type->id, 'is_active' => true]);
Group::create(['name' => 'Smoke Test Gamma', 'group_type_id' => $type->id, 'is_active' => true]);
```

**Test A — Happy path:**

- [ ] **Step 3: Prepare a happy-path CSV**

Save as `/tmp/attendance-happy.csv`:

```csv
Date,Name,Total Attendance
2025-10-05,Smoke Test Alpha,42
2025-10-12,Smoke Test Beta,17
2025-10-19,Smoke Test Gamma,55
```

- [ ] **Step 4: Import and verify**

In the admin panel, click **Import CSV**, upload the file, map `Date`→`date`, `Name`→`group`, `Total Attendance`→`total_attendance`, submit. After the import completes (a notification appears), verify the Attendance Summaries table has three new rows with the correct dates, bacentas, and totals. `submittedBy` column should be empty for all three.

**Test B — Overwrite:**

- [ ] **Step 5: Seed an existing summary with children**

In tinker:

```php
use App\Models\AttendanceSummary;
use App\Models\Group;
use App\Models\Attendance;

$group = Group::where('name', 'Smoke Test Alpha')->first();
$summary = AttendanceSummary::create([
    'group_id' => $group->id,
    'date' => '2025-11-02',
    'total_attendance' => 99,
    'visitor_count' => 7,
    'first_timer_count' => 3,
    'notes' => 'original note',
]);
Attendance::create(['attendance_summary_id' => $summary->id, 'member_id' => null, 'attended' => true, 'is_first_timer' => false, 'is_visitor' => false]);
echo "summary id: {$summary->id}\n";
```

- [ ] **Step 6: Prepare an overwrite CSV**

Save as `/tmp/attendance-overwrite.csv`:

```csv
Date,Name,Total Attendance
2025-11-02,Smoke Test Alpha,123
```

- [ ] **Step 7: Import and verify overwrite semantics**

Import the file. After completion, in tinker:

```php
$summary = App\Models\AttendanceSummary::find($summary->id); // reuse the id from Step 5
echo "total: {$summary->total_attendance}\n";       // expect 123
echo "visitor_count: {$summary->visitor_count}\n";  // expect 0
echo "notes: " . var_export($summary->notes, true) . "\n"; // expect null
echo "attendance rows: " . $summary->attendances()->count() . "\n"; // expect 0
```

All four expectations must match. If any differ, the overwrite logic is wrong — do not mark this task complete.

**Test C — Failures:**

- [ ] **Step 8: Prepare a failure CSV**

Save as `/tmp/attendance-failures.csv`:

```csv
Date,Name,Total Attendance
2025-12-07,Smoke Test Alpha,30
not-a-date,Smoke Test Beta,20
2025-12-07,Does Not Exist,10
2025-12-14,Smoke Test Gamma,-5
```

- [ ] **Step 9: Import and verify each failure is reported**

After the import finishes:

1. The completion notification body should mention `1 row imported. 3 failed.` and list `Does Not Exist` under "Unmatched bacentas".
2. In the Attendance Summaries table, only the `Smoke Test Alpha / 2025-12-07 / 30` row should be newly present (from this file).
3. Navigate to the Import History page, open the most recent import, and open the Failed Rows tab. You should see three rows: one with a date validation error, one with an unmatched bacenta (the message comes from the notification body), one with a negative-total validation error.

- [ ] **Step 10: Commit the smoke-test CSVs (optional) or discard them**

The CSVs are test scaffolding; do not commit them to the repo. Run `rm /tmp/attendance-{happy,overwrite,failures}.csv` after you're done.

---

## Done criteria

- `./vendor/bin/phpunit --filter NormalizeNameTest` green.
- Admin panel → Attendance → Attendance Summaries shows an "Import CSV" header button.
- Smoke Tests A, B, and C above all pass.
- Import History page lists the runs with accurate successful/failed counts.
- No changes to the Central panel, no new dependencies in `composer.json`.
