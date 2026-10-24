<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CampRegistration;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Public, no-login RSVP for the Living Waters & First Love Camp, mirroring the
 * Understanding Campaign welcome form (Blade, tenant-domain scoped). No payment.
 *
 * Country and denomination are short fixed lists (not the tenant's Groups), so
 * the form works regardless of how the tenant's hierarchy is set up.
 */
class CampRegistrationController extends Controller
{
    /** Whether this tenant runs the camp at all. Off everywhere by default, so
     *  deploying the shared codebase never exposes the page on an unrelated
     *  tenant; set true only on the Eurozone (go-church) tenant. */
    private const ENABLED_KEY = 'camp_enabled';

    /** The setting key that closes registration once the camp is over. */
    private const CLOSED_KEY = 'camp_registrations_closed';

    /**
     * Optional host restriction. A tenant can be served on several domains
     * (the go-church tenant answers on both gochurch.church-stack.com and the
     * Eurozone's fl-eurozone.church-stack.com). The camp is a Eurozone event,
     * so when this setting holds a host the public pages serve ONLY on that
     * host and 404 on the tenant's other domains. Blank = serve on every
     * domain of the tenant.
     */
    private const DOMAIN_KEY = 'camp_domain';

    /** Countries offered in the form. */
    public const COUNTRIES = ['Germany', 'Portugal', 'Switzerland', 'Other'];

    /** Denominations offered in the form. */
    public const DENOMINATIONS = ['First Love', 'Living Waters'];

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

    /** 404 the whole feature on any tenant that hasn't switched it on, and
     *  (when a camp_domain is set) on any domain of that tenant other than the
     *  camp's. Keeps the feature invisible everywhere it isn't wanted. */
    private function ensureEnabled(): void
    {
        abort_unless((bool) Setting::get(self::ENABLED_KEY, false), 404);

        $host = Setting::get(self::DOMAIN_KEY);
        if (filled($host)) {
            abort_unless(request()->getHost() === $host, 404);
        }
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

        return view('camp-form', [
            'camp' => static::campDetails(),
            'countries' => self::COUNTRIES,
            'denominations' => self::DENOMINATIONS,
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

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'phone_number' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'country' => ['required', 'string', Rule::in(self::COUNTRIES)],
            'denomination' => ['required', 'string', Rule::in(self::DENOMINATIONS)],
            'travelling_from' => ['nullable', 'string', 'max:255'],
            'needs_accommodation' => ['required', 'boolean'],
            'bringing_children' => ['required', 'boolean'],
            'number_of_children' => ['nullable', 'integer', 'min:0', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if (empty($data['bringing_children'])) {
            $data['number_of_children'] = null;
        }

        CampRegistration::create($data);

        // Explicit path, not route(): tenant routes register late so names don't resolve.
        return redirect('/camp/register')->with('success', true);
    }
}
