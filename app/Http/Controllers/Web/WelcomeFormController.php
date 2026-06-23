<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\UnderstandingCampaign;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WelcomeFormController extends Controller
{
    /**
     * Landing page: list the tenant's Streams, each linking to its own form.
     */
    public function index()
    {
        return view('welcome-landing', [
            'streams' => $this->streams(),
        ]);
    }

    /**
     * The form for a specific Stream (resolved from its slug).
     */
    public function show(string $stream)
    {
        $streamGroup = $this->resolveStream($stream);

        return view('welcome-form', [
            'stream' => $streamGroup,
            'streamSlug' => $stream,
        ]);
    }

    public function store(Request $request, string $stream)
    {
        $streamGroup = $this->resolveStream($stream);

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

        $data['stream_id'] = $streamGroup->id;

        UnderstandingCampaign::create($data);

        // Explicit path, not route(): tenant routes register late so names don't resolve.
        return redirect('/welcome/'.$stream)->with('success', true);
    }

    /**
     * All Stream-type groups for this tenant (GroupType slug 'stream').
     */
    private function streams()
    {
        return Group::whereHas('groupType', fn ($q) => $q->where('slug', 'stream'))
            ->orderBy('name')
            ->get();
    }

    /**
     * Resolve a Stream group from its name-slug, or 404.
     */
    private function resolveStream(string $slug): Group
    {
        $stream = $this->streams()->first(fn (Group $g) => Str::slug($g->name) === $slug);

        abort_unless($stream !== null, 404);

        return $stream;
    }
}
