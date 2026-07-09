<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AttendanceCountEntry;
use App\Models\AttendanceCounter;
use App\Models\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AttendanceCounterController extends Controller
{
    /**
     * Landing page: list the tenant's Streams, each linking to its own counter.
     */
    public function index()
    {
        return view('attendance-counter-landing', [
            'streams' => $this->streams(),
        ]);
    }

    /**
     * The kiosk tap-counter for a specific Stream (resolved from its slug).
     */
    public function show(string $stream)
    {
        $streamGroup = $this->resolveStream($stream);

        return view('attendance-counter', [
            'stream' => $streamGroup,
            'streamSlug' => $stream,
        ]);
    }

    /**
     * Record a single tap: append an audit row and increment today's summary.
     */
    public function increment(Request $request, string $stream): JsonResponse
    {
        $streamGroup = $this->resolveStream($stream);

        $validated = $request->validate([
            'category' => ['required', Rule::in(array_keys(AttendanceCounter::CATEGORY_COLUMNS))],
            'device_id' => ['nullable', 'string', 'max:255'],
        ]);

        $column = AttendanceCounter::CATEGORY_COLUMNS[$validated['category']];
        $today = Carbon::today()->toDateString();

        AttendanceCountEntry::create([
            'group_id' => $streamGroup->id,
            'date' => $today,
            'device_id' => $validated['device_id'] ?? null,
            'category' => $validated['category'],
        ]);

        $counter = AttendanceCounter::firstOrCreate([
            'group_id' => $streamGroup->id,
            'date' => $today,
        ]);
        $counter->increment($column);

        return response()->json([
            'success' => true,
            'counts' => $this->countsPayload($counter->fresh()),
        ]);
    }

    /**
     * Current running counts for today.
     */
    public function counts(Request $request, string $stream): JsonResponse
    {
        $streamGroup = $this->resolveStream($stream);

        $counter = AttendanceCounter::where('group_id', $streamGroup->id)
            ->whereDate('date', Carbon::today())
            ->first();

        return response()->json([
            'counts' => $this->countsPayload($counter),
        ]);
    }

    /**
     * Shape a counter (or null) into the JSON the kiosk expects.
     */
    private function countsPayload(?AttendanceCounter $counter): array
    {
        return [
            'first_time' => $counter->first_time_count ?? 0,
            'returning' => $counter->returning_count ?? 0,
            'regular' => $counter->regular_count ?? 0,
            'visitor' => $counter->visitor_count ?? 0,
            'total' => $counter?->total_count ?? 0,
        ];
    }

    /**
     * All Stream-type groups for this tenant (GroupType slug 'stream').
     */
    private function streams(): Collection
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
