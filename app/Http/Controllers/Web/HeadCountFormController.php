<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\HeadCount;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class HeadCountFormController extends Controller
{
    /**
     * The public ushers head-count form.
     */
    public function show()
    {
        return view('head-count-form', [
            'bacentas' => $this->bacentas(),
        ]);
    }

    /**
     * Store a submitted head count.
     */
    public function store(Request $request)
    {
        // Honeypot: silently accept spam bots without saving.
        if ($request->filled('company')) {
            return redirect('/count-heads')->with('success', true);
        }

        $bacentaIds = $this->bacentas()->pluck('id')->all();

        $data = $request->validate([
            'group_id' => ['required', Rule::in($bacentaIds)],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'total_attendance' => ['required', 'integer', 'min:0', 'max:100000'],
            'first_timer_count' => ['nullable', 'integer', 'min:0', 'max:100000', 'lte:total_attendance'],
            'visitor_count' => ['nullable', 'integer', 'min:0', 'max:100000', 'lte:total_attendance'],
            'submitter_name' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'group_id.required' => 'Please choose a bacenta. / Kies een bacenta.',
            'group_id.in' => 'Please choose a bacenta. / Kies een bacenta.',
            'first_timer_count.lte' => 'First-timers cannot be more than the total present. / Eerste keer bezoekers kan niet meer zijn dan het totaal aanwezig.',
            'visitor_count.lte' => 'Visitors cannot be more than the total present. / Bezoekers kan niet meer zijn dan het totaal aanwezig.',
        ]);

        // One head count per bacenta per day.
        $alreadyExists = HeadCount::where('group_id', $data['group_id'])
            ->whereDate('date', $data['date'])
            ->exists();

        if ($alreadyExists) {
            return back()->withInput()->withErrors([
                'date' => 'A head count for this bacenta and date has already been submitted. / Er is al een telling voor deze bacenta en datum ingediend.',
            ]);
        }

        $data['first_timer_count'] = $data['first_timer_count'] ?? 0;
        $data['visitor_count'] = $data['visitor_count'] ?? 0;

        HeadCount::create($data);

        // Explicit path, not route(): tenant routes register late so names don't resolve.
        return redirect('/count-heads')->with('success', true);
    }

    /**
     * All attendance-tracking groups (bacentas) for this tenant.
     */
    private function bacentas(): Collection
    {
        return Group::whereHas('groupType', fn ($q) => $q->where('tracks_attendance', true))
            ->orderBy('name')
            ->get();
    }
}
