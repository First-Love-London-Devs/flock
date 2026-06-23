# Understanding Campaign Form — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A public bilingual (EN/NL) web form on a Flock tenant's domain that captures first-timer/convert details into a per-tenant table, reviewed and allocated to a Bacenta in the Filament admin.

**Architecture:** A Blade form served from the tenant web routes (`routes/tenant.php`) posts to a controller that stores an `UnderstandingCampaign` row in the tenant database. A Filament resource in the existing tenant **admin** panel lists submissions and lets staff set an `allocated_group_id` (a Bacenta-type Group).

**Tech Stack:** Laravel 10, PHP 8.2, Filament 3.2, stancl/tenancy v3, Blade + `layouts.public`, Pest/PHPUnit.

## Global Constraints

- Multi-tenant (stancl/tenancy v3): per-tenant tables live in `database/migrations/tenant/`. After deploy, run `php artisan tenants:migrate --force`.
- Tenant web routes go in `routes/tenant.php`, inside the existing group with `['web', InitializeTenancyByDomain::class, PreventAccessFromCentralDomains::class]`.
- "Bacenta" = a `Group` whose `GroupType.tracks_attendance` is `true` (same identification `NonMemberResource` uses).
- All form fields are required.
- Bilingual labels show English / Dutch together, verbatim:
  - `Date` · `First Name / Voornaam` · `Surname / Achternaam` · `Street Name / Straatnaam` · `Postal Code / Postcode` · `Phone Number / Telefoonnummer` · `Are you re-dedicating your life to Christ? / Geef je jouw leven opnieuw aan Jezus?` · `Is this your first time attending this church? / Is het jouw eerste keer in deze kerk?` · `Who invited you? / Wie heeft jou uitgenodigd?`
- No auth on the public form; no notifications on submit (v1).
- Branch: `feature/understanding-campaign-form`. Filament panel discovers resources in `app/Filament/Resources`.
- Tests follow the repo pattern: extend `Tests\TestCase`, use `RefreshDatabase` (tenant migrations run into the test DB), bypass tenancy middleware with `$this->withoutMiddleware([InitializeTenancyByDomain::class, PreventAccessFromCentralDomains::class])`, and build groups via the `Tests\Concerns\BuildsGovernanceFixtures` trait (`seedGovernanceTypes()`, `makeConstituency()`, `makeCellGroup()`).

## File Structure

- `database/migrations/tenant/2026_06_23_120000_create_understanding_campaigns_table.php` — per-tenant table.
- `app/Models/UnderstandingCampaign.php` — model + `allocatedGroup()` relation.
- `app/Http/Controllers/Web/WelcomeFormController.php` — `show()` + `store()` for the public form.
- `routes/tenant.php` — add `GET/POST /welcome` to the existing `web` group.
- `resources/views/welcome-form.blade.php` — the bilingual Blade form (extends `layouts.public`).
- `app/Filament/Resources/UnderstandingCampaignResource.php` + `.../UnderstandingCampaignResource/Pages/{ListUnderstandingCampaigns,EditUnderstandingCampaign}.php` — admin review + allocation.
- Tests under `tests/Feature/UnderstandingCampaign/`.

---

### Task 1: Tenant table + UnderstandingCampaign model

**Files:**
- Create: `database/migrations/tenant/2026_06_23_120000_create_understanding_campaigns_table.php`
- Create: `app/Models/UnderstandingCampaign.php`
- Test: `tests/Feature/UnderstandingCampaign/UnderstandingCampaignModelTest.php`

**Interfaces:**
- Produces: `App\Models\UnderstandingCampaign` with `$fillable` = `attended_on, first_name, last_name, street_name, postal_code, phone_number, re_dedicating, first_time, who_invited, allocated_group_id`; casts `attended_on`→date, `re_dedicating`/`first_time`→boolean; relation `allocatedGroup(): BelongsTo` → `Group` on `allocated_group_id`. Table `understanding_campaigns`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/UnderstandingCampaign/UnderstandingCampaignModelTest.php`:

```php
<?php

namespace Tests\Feature\UnderstandingCampaign;

use App\Models\UnderstandingCampaign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnderstandingCampaignModelTest extends TestCase
{
    use RefreshDatabase;

    private function validAttributes(array $overrides = []): array
    {
        return array_merge([
            'attended_on' => '2026-06-22',
            'first_name' => 'Jan',
            'last_name' => 'Janssen',
            'street_name' => 'Kerkstraat 1',
            'postal_code' => '3000',
            'phone_number' => '+32470000000',
            're_dedicating' => true,
            'first_time' => true,
            'who_invited' => 'Piet',
        ], $overrides);
    }

    public function test_it_persists_a_submission_with_casts(): void
    {
        $uc = UnderstandingCampaign::create($this->validAttributes());

        $fresh = $uc->fresh();
        $this->assertTrue($fresh->re_dedicating);
        $this->assertTrue($fresh->first_time);
        $this->assertSame('2026-06-22', $fresh->attended_on->toDateString());
        $this->assertNull($fresh->allocated_group_id);
        $this->assertDatabaseHas('understanding_campaigns', [
            'first_name' => 'Jan',
            'last_name' => 'Janssen',
            'who_invited' => 'Piet',
        ]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=UnderstandingCampaignModelTest`
Expected: FAIL — `Class "App\Models\UnderstandingCampaign" not found` (and/or missing table).

- [ ] **Step 3: Create the migration**

Create `database/migrations/tenant/2026_06_23_120000_create_understanding_campaigns_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('understanding_campaigns', function (Blueprint $table) {
            $table->id();
            $table->date('attended_on');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('street_name');
            $table->string('postal_code');
            $table->string('phone_number');
            $table->boolean('re_dedicating')->default(false);
            $table->boolean('first_time')->default(false);
            $table->string('who_invited');
            $table->foreignId('allocated_group_id')->nullable()->constrained('groups')->nullOnDelete();
            $table->timestamps();

            $table->index('allocated_group_id');
            $table->index('attended_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('understanding_campaigns');
    }
};
```

- [ ] **Step 4: Create the model**

Create `app/Models/UnderstandingCampaign.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnderstandingCampaign extends Model
{
    protected $fillable = [
        'attended_on',
        'first_name',
        'last_name',
        'street_name',
        'postal_code',
        'phone_number',
        're_dedicating',
        'first_time',
        'who_invited',
        'allocated_group_id',
    ];

    protected $casts = [
        'attended_on' => 'date',
        're_dedicating' => 'boolean',
        'first_time' => 'boolean',
    ];

    public function allocatedGroup(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'allocated_group_id');
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=UnderstandingCampaignModelTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/tenant/2026_06_23_120000_create_understanding_campaigns_table.php app/Models/UnderstandingCampaign.php tests/Feature/UnderstandingCampaign/UnderstandingCampaignModelTest.php
git commit -m "feat: understanding_campaigns table + model"
```

---

### Task 2: Public /welcome form (route + controller + Blade)

**Files:**
- Create: `app/Http/Controllers/Web/WelcomeFormController.php`
- Modify: `routes/tenant.php` (add two routes inside the existing `web` group)
- Create: `resources/views/welcome-form.blade.php`
- Test: `tests/Feature/UnderstandingCampaign/WelcomeFormTest.php`

**Interfaces:**
- Consumes: `App\Models\UnderstandingCampaign` (Task 1).
- Produces: named routes `welcome-form.show` (`GET /welcome`) and `welcome-form.store` (`POST /welcome`); controller `App\Http\Controllers\Web\WelcomeFormController` with `show()` and `store(Request)`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/UnderstandingCampaign/WelcomeFormTest.php`:

```php
<?php

namespace Tests\Feature\UnderstandingCampaign;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use Tests\TestCase;

class WelcomeFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([
            InitializeTenancyByDomain::class,
            PreventAccessFromCentralDomains::class,
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'attended_on' => '2026-06-22',
            'first_name' => 'Jan',
            'last_name' => 'Janssen',
            'street_name' => 'Kerkstraat 1',
            'postal_code' => '3000',
            'phone_number' => '+32470000000',
            're_dedicating' => '1',
            'first_time' => '1',
            'who_invited' => 'Piet',
        ], $overrides);
    }

    public function test_form_renders_with_dutch_labels(): void
    {
        $this->get('/welcome')
            ->assertOk()
            ->assertSee('Voornaam')
            ->assertSee('Wie heeft jou uitgenodigd?');
    }

    public function test_valid_submission_is_stored(): void
    {
        $this->post('/welcome', $this->payload())
            ->assertRedirect(route('welcome-form.show'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('understanding_campaigns', [
            'first_name' => 'Jan',
            'last_name' => 'Janssen',
            're_dedicating' => true,
            'first_time' => true,
            'who_invited' => 'Piet',
        ]);
    }

    public function test_missing_required_field_is_rejected(): void
    {
        $this->post('/welcome', $this->payload(['first_name' => '']))
            ->assertSessionHasErrors('first_name');

        $this->assertDatabaseCount('understanding_campaigns', 0);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=WelcomeFormTest`
Expected: FAIL — route `/welcome` not defined (404 / `Route [welcome-form.show] not defined`).

- [ ] **Step 3: Create the controller**

Create `app/Http/Controllers/Web/WelcomeFormController.php`:

```php
<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\UnderstandingCampaign;
use Illuminate\Http\Request;

class WelcomeFormController extends Controller
{
    public function show()
    {
        return view('welcome-form');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'attended_on' => ['required', 'date'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'street_name' => ['required', 'string', 'max:255'],
            'postal_code' => ['required', 'string', 'max:255'],
            'phone_number' => ['required', 'string', 'max:255'],
            're_dedicating' => ['required', 'boolean'],
            'first_time' => ['required', 'boolean'],
            'who_invited' => ['required', 'string', 'max:255'],
        ]);

        UnderstandingCampaign::create($data);

        return redirect()
            ->route('welcome-form.show')
            ->with('success', true);
    }
}
```

- [ ] **Step 4: Add the routes**

In `routes/tenant.php`, add the import near the top (after the existing `use` lines):

```php
use App\Http\Controllers\Web\WelcomeFormController;
```

Then add these two routes **inside** the existing `web` group (the one with `'web', InitializeTenancyByDomain::class, PreventAccessFromCentralDomains::class`), alongside the `Route::get('/', …)`:

```php
    Route::get('/welcome', [WelcomeFormController::class, 'show'])->name('welcome-form.show');
    Route::post('/welcome', [WelcomeFormController::class, 'store'])->name('welcome-form.store');
```

- [ ] **Step 5: Create the Blade view**

Create `resources/views/welcome-form.blade.php`:

```blade
@extends('layouts.public')

@section('title', 'Welcome / Welkom')

@section('content')
    <h1 class="page-title">Welcome / Welkom</h1>
    <p class="page-subtitle">We'd love to know you. Please share your details. / We willen je graag leren kennen. Vul hieronder je gegevens in.</p>

    @if (session('success'))
        <div class="card" style="border-color:#16a34a;background:#f0fdf4;">
            <h3 style="color:#16a34a;">Thank you! / Bedankt!</h3>
            <p>We've received your details and someone will be in touch. / We hebben je gegevens ontvangen en nemen contact met je op.</p>
        </div>
    @endif

    @if ($errors->any())
        <div class="card" style="border-color:#dc2626;background:#fef2f2;">
            <p style="color:#dc2626;">Please complete all fields. / Vul alle velden in.</p>
        </div>
    @endif

    <form method="POST" action="{{ route('welcome-form.store') }}" class="card">
        @csrf

        <label>Date
            <input type="date" name="attended_on" value="{{ old('attended_on', now()->toDateString()) }}" required>
        </label>

        <label>First Name / Voornaam
            <input type="text" name="first_name" value="{{ old('first_name') }}" required>
        </label>

        <label>Surname / Achternaam
            <input type="text" name="last_name" value="{{ old('last_name') }}" required>
        </label>

        <label>Street Name / Straatnaam
            <input type="text" name="street_name" value="{{ old('street_name') }}" required>
        </label>

        <label>Postal Code / Postcode
            <input type="text" name="postal_code" value="{{ old('postal_code') }}" required>
        </label>

        <label>Phone Number / Telefoonnummer
            <input type="tel" name="phone_number" value="{{ old('phone_number') }}" required>
        </label>

        <fieldset>
            <legend>Are you re-dedicating your life to Christ? / Geef je jouw leven opnieuw aan Jezus?</legend>
            <label><input type="radio" name="re_dedicating" value="1" {{ old('re_dedicating') === '1' ? 'checked' : '' }} required> Yes / Ja</label>
            <label><input type="radio" name="re_dedicating" value="0" {{ old('re_dedicating') === '0' ? 'checked' : '' }}> No / Nee</label>
        </fieldset>

        <fieldset>
            <legend>Is this your first time attending this church? / Is het jouw eerste keer in deze kerk?</legend>
            <label><input type="radio" name="first_time" value="1" {{ old('first_time') === '1' ? 'checked' : '' }} required> Yes / Ja</label>
            <label><input type="radio" name="first_time" value="0" {{ old('first_time') === '0' ? 'checked' : '' }}> No / Nee</label>
        </fieldset>

        <label>Who invited you? / Wie heeft jou uitgenodigd?
            <input type="text" name="who_invited" value="{{ old('who_invited') }}" required>
        </label>

        <button type="submit">Submit / Verstuur</button>
    </form>
@endsection
```

(If `layouts.public` provides no form styling, add a small `@push('styles')`/inline `<style>` block for `label{display:block;margin:.75rem 0}` etc. — match the look of the existing `support.blade.php`/`privacy.blade.php` pages.)

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test --filter=WelcomeFormTest`
Expected: PASS (all three).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Web/WelcomeFormController.php routes/tenant.php resources/views/welcome-form.blade.php tests/Feature/UnderstandingCampaign/WelcomeFormTest.php
git commit -m "feat: public /welcome first-timer form"
```

---

### Task 3: Filament "Understanding Campaign" resource + Bacenta allocation

**Files:**
- Create: `app/Filament/Resources/UnderstandingCampaignResource.php`
- Create: `app/Filament/Resources/UnderstandingCampaignResource/Pages/ListUnderstandingCampaigns.php`
- Create: `app/Filament/Resources/UnderstandingCampaignResource/Pages/EditUnderstandingCampaign.php`
- Test: `tests/Feature/UnderstandingCampaign/AllocationTest.php`

**Interfaces:**
- Consumes: `App\Models\UnderstandingCampaign` + `allocatedGroup()` (Task 1); `Group`/`GroupType` (existing) where `GroupType.tracks_attendance = true` identifies a Bacenta.
- Produces: a Filament resource registered in the `admin` panel; an allocated submission persists `allocated_group_id` and resolves `allocatedGroup`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/UnderstandingCampaign/AllocationTest.php`:

```php
<?php

namespace Tests\Feature\UnderstandingCampaign;

use App\Filament\Resources\UnderstandingCampaignResource;
use App\Models\UnderstandingCampaign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsGovernanceFixtures;
use Tests\TestCase;

class AllocationTest extends TestCase
{
    use RefreshDatabase, BuildsGovernanceFixtures;

    public function test_submission_can_be_allocated_to_a_bacenta(): void
    {
        $this->seedGovernanceTypes();
        $constituency = $this->makeConstituency();
        $bacenta = $this->makeCellGroup($constituency); // a tracks_attendance group

        $uc = UnderstandingCampaign::create([
            'attended_on' => '2026-06-22',
            'first_name' => 'Jan',
            'last_name' => 'Janssen',
            'street_name' => 'Kerkstraat 1',
            'postal_code' => '3000',
            'phone_number' => '+32470000000',
            're_dedicating' => false,
            'first_time' => true,
            'who_invited' => 'Piet',
        ]);

        $uc->update(['allocated_group_id' => $bacenta->id]);

        $this->assertSame($bacenta->id, $uc->fresh()->allocated_group_id);
        $this->assertSame($bacenta->name, $uc->fresh()->allocatedGroup->name);
    }

    public function test_resource_registers_list_and_edit_pages(): void
    {
        $pages = UnderstandingCampaignResource::getPages();

        $this->assertArrayHasKey('index', $pages);
        $this->assertArrayHasKey('edit', $pages);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=AllocationTest`
Expected: FAIL — `Class "App\Filament\Resources\UnderstandingCampaignResource" not found`.

- [ ] **Step 3: Create the resource**

Create `app/Filament/Resources/UnderstandingCampaignResource.php`:

```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UnderstandingCampaignResource\Pages;
use App\Models\UnderstandingCampaign;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UnderstandingCampaignResource extends Resource
{
    protected static ?string $model = UnderstandingCampaign::class;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationGroup = 'People';

    protected static ?string $navigationLabel = 'Understanding Campaign';

    protected static ?string $modelLabel = 'Understanding Campaign entry';

    protected static ?string $recordTitleAttribute = 'first_name';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Submission')
                ->schema([
                    Forms\Components\DatePicker::make('attended_on')->label('Date')->disabled(),
                    Forms\Components\TextInput::make('first_name')->disabled(),
                    Forms\Components\TextInput::make('last_name')->label('Surname')->disabled(),
                    Forms\Components\TextInput::make('street_name')->disabled(),
                    Forms\Components\TextInput::make('postal_code')->disabled(),
                    Forms\Components\TextInput::make('phone_number')->disabled(),
                    Forms\Components\Toggle::make('re_dedicating')->label('Re-dedicating their life to Christ')->disabled(),
                    Forms\Components\Toggle::make('first_time')->label('First time at this church')->disabled(),
                    Forms\Components\TextInput::make('who_invited')->disabled(),
                ])->columns(2),

            Forms\Components\Section::make('Allocation')
                ->schema([
                    Forms\Components\Select::make('allocated_group_id')
                        ->label('Allocated Bacenta')
                        ->relationship(
                            'allocatedGroup',
                            'name',
                            fn ($query) => $query->whereHas('groupType', fn ($q) => $q->where('tracks_attendance', true)),
                        )
                        ->searchable()
                        ->preload(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('attended_on')->label('Date')->date()->sortable(),
                Tables\Columns\TextColumn::make('first_name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('last_name')->label('Surname')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('phone_number')->searchable(),
                Tables\Columns\IconColumn::make('first_time')->label('First-timer')->boolean(),
                Tables\Columns\IconColumn::make('re_dedicating')->label('Re-dedicating')->boolean(),
                Tables\Columns\TextColumn::make('who_invited')->toggleable(),
                Tables\Columns\TextColumn::make('allocatedGroup.name')->label('Allocated Bacenta')->placeholder('—')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('attended_on', 'desc')
            ->filters([
                Tables\Filters\Filter::make('unallocated')
                    ->label('Not yet allocated')
                    ->query(fn ($query) => $query->whereNull('allocated_group_id')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUnderstandingCampaigns::route('/'),
            'edit' => Pages\EditUnderstandingCampaign::route('/{record}/edit'),
        ];
    }
}
```

- [ ] **Step 4: Create the page classes**

Create `app/Filament/Resources/UnderstandingCampaignResource/Pages/ListUnderstandingCampaigns.php`:

```php
<?php

namespace App\Filament\Resources\UnderstandingCampaignResource\Pages;

use App\Filament\Resources\UnderstandingCampaignResource;
use Filament\Resources\Pages\ListRecords;

class ListUnderstandingCampaigns extends ListRecords
{
    protected static string $resource = UnderstandingCampaignResource::class;
}
```

Create `app/Filament/Resources/UnderstandingCampaignResource/Pages/EditUnderstandingCampaign.php`:

```php
<?php

namespace App\Filament\Resources\UnderstandingCampaignResource\Pages;

use App\Filament\Resources\UnderstandingCampaignResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUnderstandingCampaign extends EditRecord
{
    protected static string $resource = UnderstandingCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=AllocationTest`
Expected: PASS (both tests).

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Resources/UnderstandingCampaignResource.php app/Filament/Resources/UnderstandingCampaignResource tests/Feature/UnderstandingCampaign/AllocationTest.php
git commit -m "feat: Understanding Campaign Filament resource + bacenta allocation"
```

---

### Task 4: Validate, manual browser check, finalize

**Files:** none (verification + final commit if pint reformats).

- [ ] **Step 1: Lint**

Run: `./vendor/bin/pint app/Models/UnderstandingCampaign.php app/Http/Controllers/Web/WelcomeFormController.php app/Filament/Resources/UnderstandingCampaignResource.php app/Filament/Resources/UnderstandingCampaignResource/Pages routes/tenant.php`
Expected: PASS (or auto-fixes; re-commit if it changes files).

- [ ] **Step 2: Run the full feature suite for this area**

Run: `php artisan test --filter=UnderstandingCampaign`
Expected: PASS (all model, form, and allocation tests).

- [ ] **Step 3: Run the whole suite to confirm no regressions**

Run: `php artisan test`
Expected: no NEW failures versus the pre-change baseline.

- [ ] **Step 4: Manual browser verification (no Filament test harness in repo)**

On a local/staging tenant (or after deploy, on `gochurch.church-stack.com`):
- Visit `/welcome` → form renders with bilingual labels; submitting valid data shows the thank-you message; an empty field is rejected.
- In `/admin` → "Understanding Campaign" appears; the new submission is listed; opening it and choosing an **Allocated Bacenta** saves and shows in the list column.

- [ ] **Step 5: Commit any pint changes**

```bash
git add -A
git commit -m "style: pint" || echo "nothing to commit"
```

---

## Deployment

- Merge `feature/understanding-campaign-form` → `main` (Forge Quick Deploy auto-deploys).
- After deploy, run `php artisan tenants:migrate --force` so every tenant gets the `understanding_campaigns` table (gate on owner per Flock deploy convention).

## Self-Review

- **Spec coverage:** public bilingual form (Task 2) ✓; all-required validation (Task 2) ✓; per-tenant table (Task 1) ✓; Understanding Campaign admin + Allocated Bacenta (Task 3) ✓; no notifications/auth (omitted by design) ✓; tenant migration deploy note ✓.
- **Placeholders:** none — every step has concrete code/commands.
- **Type consistency:** `allocated_group_id` / `allocatedGroup()` / `understanding_campaigns` / route names `welcome-form.show|store` used consistently across tasks. Bacenta filter (`groupType.tracks_attendance = true`) matches `NonMemberResource`.
