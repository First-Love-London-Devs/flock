<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AttendanceCountEntry;
use App\Models\AttendanceCounter;
use App\Models\Group;
use App\Services\DomainScope;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
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

        $counter = $this->counterFor($streamGroup->id, $today);
        $counter->increment($column);

        return response()->json([
            'success' => true,
            'counts' => $this->countsPayload($counter->fresh()),
        ]);
    }

    /**
     * Undo the last tap in a category.
     *
     * Ushers miscount. Until now the only way to fix a stray tap was to ask an
     * admin to change the number afterwards, by which point nobody remembers
     * which category it was.
     *
     * The audit row is removed as well as the summary decremented, so the tap
     * log and the total stay in step. Editing one without the other is how a
     * count and its own history start disagreeing.
     *
     * Refuses to go below zero: a negative attendance is never a correction,
     * it is a second mistake on top of the first.
     */
    public function undo(Request $request, string $stream): JsonResponse
    {
        $streamGroup = $this->resolveStream($stream);

        $validated = $request->validate([
            'category' => ['required', Rule::in(array_keys(AttendanceCounter::CATEGORY_COLUMNS))],
        ]);

        $column = AttendanceCounter::CATEGORY_COLUMNS[$validated['category']];
        $today = Carbon::today()->toDateString();

        $counter = AttendanceCounter::where('group_id', $streamGroup->id)
            ->whereDate('date', $today)
            ->first();

        if (! $counter || $counter->{$column} < 1) {
            return response()->json([
                'success' => false,
                'message' => 'Nothing to undo in that category today.',
                'counts' => $this->countsPayload($counter),
            ], 422);
        }

        DB::transaction(function () use ($streamGroup, $today, $validated, $counter, $column) {
            /* The most recent tap in this category, whichever device made it:
               an usher correcting a miscount is often not the one who made it. */
            $last = AttendanceCountEntry::where('group_id', $streamGroup->id)
                ->whereDate('date', $today)
                ->where('category', $validated['category'])
                ->latest('id')
                ->first();

            $last?->delete();

            $counter->decrement($column);
        });

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
    /**
     * The streams this address is allowed to count for.
     *
     * The counter is a public kiosk, so there is no login to scope by; the
     * address it was opened at is all we have. DomainScope turns that into a
     * country when the domain names one, and narrows nothing when it does not,
     * so the group-wide address still lists everything.
     *
     * This listed every stream in the tenant. That went unnoticed while
     * Belgium was the only country with any, and the moment Switzerland got
     * eight, Belgium's ushers were offered Swiss churches to count for. It
     * also guards resolveStream(), so a Belgian address cannot open a Swiss
     * counter by URL either.
     */
    private function streams(): Collection
    {
        $allowed = DomainScope::groupIds();

        return Group::whereHas('groupType', fn ($q) => $q->where('slug', 'stream'))
            ->when($allowed !== null, fn ($q) => $q->whereIn('groups.id', $allowed))
            ->orderBy('name')
            ->get();
    }

    /**
     * Today's counter for a stream, created if this is the first tap.
     *
     * Deliberately not firstOrCreate on ['group_id', 'date']. The `date` cast
     * writes a full datetime, and whether that then matches a bare date on the
     * way back depends on the database: MySQL's DATE column truncates it, so
     * production is fine, while SQLite keeps the time and the second tap of
     * the day fails the unique index instead of finding the existing row.
     * Relying on one engine's truncation is not something to leave in place.
     */
    private function counterFor(int $groupId, string $date): AttendanceCounter
    {
        $existing = AttendanceCounter::where('group_id', $groupId)
            ->whereDate('date', $date)
            ->first();

        if ($existing) {
            return $existing;
        }

        try {
            return AttendanceCounter::create(['group_id' => $groupId, 'date' => $date]);
        } catch (UniqueConstraintViolationException $e) {
            /* Two ushers tapping at the same second on the first tap of the
               day. The row now exists, so read it rather than failing. */
            return AttendanceCounter::where('group_id', $groupId)
                ->whereDate('date', $date)
                ->firstOrFail();
        }
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
