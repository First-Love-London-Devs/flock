<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CampRegistration;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Public, no-login RSVP for the Eurozone camp, mirroring the Understanding
 * Campaign welcome form (Blade, tenant-domain scoped). No payment.
 *
 * The page serves ONE camp at a time (campDetails()). It first ran the Basel
 * camp (Nov 2026) and now runs the Copenhagen Camp (Dec 2026); every
 * registration is tagged with the camp slug it was taken for, so the admin
 * list can tell the two apart.
 *
 * The registrant's church is a short fixed list (not the tenant's Groups), so
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

    /** Churches offered in the form: the branches travelling to Copenhagen. */
    public const CHURCHES = [
        'Go Church',
        'Go Ye Church',
        'First Love Sweden',
        'First Love Portugal',
        'First Love Bulgaria',
    ];

    /**
     * Every camp this page has run for, newest first, keyed by the slug
     * stored on each registration. Older entries stay so the admin list can
     * still filter the registrations they collected.
     */
    public const CAMPS = [
        'copenhagen-2026' => 'Copenhagen Camp (Dec 2026)',
        'basel-2026' => 'Living Waters & First Love Camp, Basel (Nov 2026)',
    ];

    /**
     * Fixed facts for the camp currently on the page. Kept here (not a
     * per-tenant setting) because each camp is a one-off; swap this block when
     * the next camp comes round and add its slug to CAMPS.
     */
    public static function campDetails(): array
    {
        return [
            'slug' => 'copenhagen-2026',
            'name' => 'Copenhagen Camp',  // named after the organising church; held in Dijon
            'city' => 'Dijon',
            'country' => 'France',
            'venue' => null,    // to be announced
            'address' => null,
            'start_date' => '2026-12-03',
            'end_date' => '2026-12-04',
            'schedule' => [
                ['date' => '2026-12-02', 'label' => 'Arrival of campers', 'detail' => 'Evening'],
                ['date' => '2026-12-03', 'label' => 'Sessions', 'detail' => 'All day'],
                ['date' => '2026-12-04', 'label' => 'Sessions', 'detail' => 'Morning (to be confirmed)'],
                ['date' => '2026-12-04', 'label' => 'Departure', 'detail' => 'Afternoon'],
            ],
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
            'churches' => self::CHURCHES,
            'closed' => $this->registrationsClosed(),
        ]);
    }

    public function register()
    {
        $this->ensureEnabled();

        return view('camp-form', [
            'camp' => static::campDetails(),
            'churches' => self::CHURCHES,
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
            'church' => ['required', 'string', Rule::in(self::CHURCHES)],
            'travelling_from' => ['nullable', 'string', 'max:255'],
            'needs_accommodation' => ['required', 'boolean'],
            'bringing_children' => ['required', 'boolean'],
            'number_of_children' => ['nullable', 'integer', 'min:0', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if (empty($data['bringing_children'])) {
            $data['number_of_children'] = null;
        }

        // Only validated keys reach the model, so a replayed Basel-era payload
        // (country/denomination) cannot write onto a Copenhagen row.
        $data['camp'] = static::campDetails()['slug'];

        CampRegistration::create($data);

        // Explicit path, not route(): tenant routes register late so names don't resolve.
        return redirect('/camp/register')->with('success', true);
    }
}
