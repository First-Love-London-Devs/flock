<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CampRegistration;
use App\Models\Group;
use App\Models\Setting;
use App\Services\DomainScope;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Public, no-login RSVP for the Living Waters & First Love Camp, mirroring the
 * Understanding Campaign welcome form (Blade, tenant-domain scoped). No payment.
 *
 * The form offers a country + church picker drawn from the tenant's Groups, so
 * an RSVP records which church the registrant belongs to. On a country
 * subdomain DomainScope narrows the pickers to that country; on the tenant-wide
 * address every country/church is offered.
 */
class CampRegistrationController extends Controller
{
    /** Whether this tenant runs the camp at all. Off everywhere by default, so
     *  deploying the shared codebase never exposes the page on an unrelated
     *  tenant (gochurch, grace, …); set true only on the eurozone tenant. */
    private const ENABLED_KEY = 'camp_enabled';

    /** The setting key that closes registration once the camp is over. */
    private const CLOSED_KEY = 'camp_registrations_closed';

    /**
     * Fixed camp facts. Kept here (not a per-tenant setting) because this is a
     * one-off event; move to config/Settings if it becomes recurring.
     */
    public static function campDetails(): array
    {
        return [
            'name' => 'Living Waters & First Love Camp',
            'speaker' => 'Bishop Dag Heward-Mills',
            'start_date' => '2026-11-16',
            'end_date' => '2026-11-20',
            'venue' => 'Klybeck 610 Event-Plattform',
            'address' => 'Gärtnerstrasse 2, 4057 Basel',
        ];
    }

    /** The individual camp days, as Y-m-d strings (16-20 Nov). */
    public static function campDates(): array
    {
        $details = static::campDetails();
        $dates = [];
        $day = Carbon::parse($details['start_date']);
        $end = Carbon::parse($details['end_date']);
        while ($day->lte($end)) {
            $dates[] = $day->toDateString();
            $day = $day->copy()->addDay();
        }

        return $dates;
    }

    /** 404 the whole feature on any tenant that hasn't switched it on. */
    private function ensureEnabled(): void
    {
        abort_unless((bool) Setting::get(self::ENABLED_KEY, false), 404);
    }

    private function registrationsClosed(): bool
    {
        return (bool) Setting::get(self::CLOSED_KEY, false);
    }

    public function landing()
    {
        $this->ensureEnabled();

        return view('camp-landing', [
            'camp' => static::campDetails(),
            'closed' => $this->registrationsClosed(),
        ]);
    }

    public function register()
    {
        $this->ensureEnabled();

        [$countries, $churches] = $this->pickerData();

        return view('camp-form', [
            'camp' => static::campDetails(),
            'dates' => static::campDates(),
            'countries' => $countries,
            'churches' => $churches,
            'closed' => $this->registrationsClosed(),
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureEnabled();

        // UI state is never trusted: re-check the gate server-side.
        if ($this->registrationsClosed()) {
            return redirect('/camp/register')->with('closed', true);
        }

        $dates = static::campDates();

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'phone_number' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'country_group_id' => ['required', 'integer', 'exists:groups,id'],
            'church_group_id' => ['required', 'integer', 'exists:groups,id'],
            'attending_dates' => ['required', 'array', 'min:1'],
            'attending_dates.*' => ['string', Rule::in($dates)],
            'travelling_from' => ['nullable', 'string', 'max:255'],
            'needs_accommodation' => ['required', 'boolean'],
            'bringing_children' => ['required', 'boolean'],
            'number_of_children' => ['nullable', 'integer', 'min:0', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        // The church must be a stream that actually sits under the chosen
        // country, and both must be offered on this domain. Stops a crafted
        // POST recording a mismatched or out-of-country pairing.
        [$countries, $churches] = $this->pickerData();
        $church = $churches->firstWhere('id', (int) $data['church_group_id']);
        abort_unless(
            $church !== null
                && $countries->contains('id', (int) $data['country_group_id'])
                && $church['country_id'] === (int) $data['country_group_id'],
            422,
            'Please choose a church that belongs to the selected country.'
        );

        if (empty($data['bringing_children'])) {
            $data['number_of_children'] = null;
        }

        CampRegistration::create($data);

        // Explicit path, not route(): tenant routes register late so names don't resolve.
        return redirect('/camp/register')->with('success', true);
    }

    /**
     * Countries and churches offered in the pickers, narrowed to the country
     * this domain belongs to (DomainScope). Each church carries its country id
     * so the form can filter churches by the selected country.
     *
     * @return array{0: Collection, 1: Collection}
     */
    private function pickerData(): array
    {
        // One load of the tenant's groups, so resolving each church's country
        // ancestor doesn't issue a query per church.
        $all = Group::with('groupType')->get()->keyBy('id');
        $allowed = DomainScope::groupIds();

        $isType = fn (?Group $g, string $slug): bool => $g !== null
            && optional($g->groupType)->slug !== null
            && strtolower($g->groupType->slug) === $slug;

        $countryOf = function (Group $group) use ($all, $isType): ?Group {
            $current = $group;
            $depth = 20;
            while ($current && $depth-- > 0) {
                if ($isType($current, 'country')) {
                    return $current;
                }
                $current = $current->parent_id ? ($all[$current->parent_id] ?? null) : null;
            }

            return null;
        };

        $inScope = fn (Group $g): bool => $allowed === null || $allowed->contains($g->id);

        $countries = $all->filter(fn (Group $g) => $isType($g, 'country') && $inScope($g))
            ->sortBy('name')
            ->map(fn (Group $g) => ['id' => $g->id, 'name' => $g->name])
            ->values();

        $churches = $all->filter(fn (Group $g) => $isType($g, 'stream') && $inScope($g))
            ->map(function (Group $g) use ($countryOf) {
                $country = $countryOf($g);

                return ['id' => $g->id, 'name' => $g->name, 'country_id' => $country?->id, 'country_name' => $country?->name];
            })
            ->filter(fn (array $c) => $c['country_id'] !== null)
            ->sortBy('name')
            ->values();

        return [$countries, $churches];
    }
}
