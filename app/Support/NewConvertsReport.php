<?php

namespace App\Support;

use App\Models\Attendance;
use App\Models\Group;
use App\Models\Member;
use App\Models\NonMemberAttendance;
use App\Models\UnderstandingCampaign;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Who was marked a new convert, when, and where.
 *
 * The panel could already tell you how MANY new converts a service had:
 * AttendanceSummary carries a new_convert_count and the dashboards add it up.
 * It could not tell you WHO, which is the only version of that number anyone
 * can act on.
 *
 * The list has to span two tables, and that is the whole reason this class
 * exists rather than a filter on the members table. A new convert is flagged
 * on an attendance record, and attendance records point at two different kinds
 * of person: someone already on the roll, and someone who is not. In the
 * go-church tenant that split is 94 members against 98 non-members, so a
 * members-only list would miss half of them. Meanwhile only 13 people carry
 * member_type = new_convert, which is a classification somebody sets by hand
 * and is not where the flow of new converts actually lands.
 *
 * One row per person, not per service: someone marked on three Sundays is one
 * person to follow up, with the date they first appeared and the date they
 * were last seen.
 *
 * ⚠ THERE IS A THIRD SOURCE, added later: the public welcome form. Some
 * churches never tick the register at all and their people arrive entirely
 * through that form, so a list built only on attendance flags showed those
 * churches nothing. See welcomeForm() for which answer counts and why.
 */
class NewConvertsReport
{
    /**
     * @param  string|null  $from  inclusive date, 'Y-m-d'
     * @param  string|null  $to  inclusive date, 'Y-m-d'
     * @param  int|null  $service  a stream group; everything beneath it counts
     * @param  int|null  $group  one exact group, usually a bacenta
     * @return Collection<int, array>
     */
    public static function rows(
        ?string $from = null,
        ?string $to = null,
        ?int $service = null,
        ?int $group = null,
    ): Collection {
        $scope = self::narrow(User::currentScopeIds(), $service, $group);

        /*
         * Start from a plain collection rather than merging into whichever one
         * the first source happened to return. Each source builds rows of
         * arrays, but they start life as Eloquent collections, and Eloquent's
         * merge expects models: it calls getKey() on every incoming item and
         * dies on an array. Which source is empty then decides whether the
         * whole report works, which is not a thing that should decide anything.
         */
        return collect()
            ->merge(self::members($scope, $from, $to))
            ->merge(self::nonMembers($scope, $from, $to))
            ->merge(self::welcomeForm($scope, $from, $to))
            ->sortByDesc('last_seen')
            ->values();
    }

    /**
     * Fold the two filters into the scope rather than applying them separately.
     *
     * Both narrow the same thing — which groups' attendance counts — so they
     * belong in the same set of ids as the admin's own confinement. Doing it
     * this way makes it impossible to write a filter that widens: the result
     * is always an intersection, so picking a service in another country
     * returns nothing rather than that country.
     *
     * A service means the stream and everything under it, because the flag
     * sits on a bacenta's attendance, not on the stream's.
     */
    private static function narrow(?Collection $scope, ?int $service, ?int $group): ?Collection
    {
        if ($service === null && $group === null) {
            return $scope;
        }

        $wanted = $service !== null
            ? (Group::find($service)?->allGroupIds() ?? collect())
            : collect();

        if ($group !== null) {
            $wanted = $service !== null && ! $wanted->contains($group)
                // A group outside the chosen service: the two disagree, and
                // the honest answer to a contradiction is nothing.
                ? collect()
                : collect([$group]);
        }

        return $scope === null ? $wanted->values() : $wanted->intersect($scope)->values();
    }

    private static function members($scope, ?string $from, ?string $to): Collection
    {
        $rows = Attendance::query()
            ->where('is_new_convert', true)
            ->whereHas('attendanceSummary', fn ($q) => self::constrain($q, $scope, $from, $to))
            ->with([
                'member:id,first_name,last_name,phone_number,email,nbs_status,member_type',
                'attendanceSummary:id,group_id,date',
                'attendanceSummary.group:id,name',
            ])
            ->get()
            ->filter(fn ($a) => $a->member !== null)
            ->groupBy('member_id');

        return $rows->map(function ($flags) {
            $member = $flags->first()->member;
            $latest = $flags->sortByDesc(fn ($f) => $f->attendanceSummary?->date)->first();

            return [
                'name' => trim($member->first_name.' '.$member->last_name),
                'phone' => $member->phone_number,
                'email' => $member->email,
                'on_roll' => 'Member',
                'group' => $latest->attendanceSummary?->group?->name,
                'first_seen' => self::date($flags->min(fn ($f) => $f->attendanceSummary?->date?->toDateString())),
                'last_seen' => self::date($flags->max(fn ($f) => $f->attendanceSummary?->date?->toDateString())),
                'times_marked' => $flags->count(),
                'nbs_status' => $member->nbs_status
                    ? (Member::NBS_STATUSES[$member->nbs_status] ?? $member->nbs_status)
                    : '',
            ];
        })->values();
    }

    private static function nonMembers($scope, ?string $from, ?string $to): Collection
    {
        $rows = NonMemberAttendance::query()
            ->where('is_new_convert', true)
            ->whereHas('summary', fn ($q) => self::constrain($q, $scope, $from, $to))
            ->with([
                'nonMember:id,first_name,last_name,phone_number,email',
                'summary:id,group_id,date',
                'summary.group:id,name',
            ])
            ->get()
            ->filter(fn ($a) => $a->nonMember !== null)
            ->groupBy('non_member_id');

        return $rows->map(function ($flags) {
            $person = $flags->first()->nonMember;
            $latest = $flags->sortByDesc(fn ($f) => $f->summary?->date)->first();

            return [
                'name' => trim($person->first_name.' '.$person->last_name),
                'phone' => $person->phone_number,
                'email' => $person->email,
                // The distinction that matters for follow-up: this person has
                // no record on the roll, so someone has to create one.
                'on_roll' => 'Not on roll',
                'group' => $latest->summary?->group?->name,
                'first_seen' => self::date($flags->min(fn ($f) => $f->summary?->date?->toDateString())),
                'last_seen' => self::date($flags->max(fn ($f) => $f->summary?->date?->toDateString())),
                'times_marked' => $flags->count(),
                'nbs_status' => '',
            ];
        })->values();
    }

    /**
     * People who came in through the welcome form rather than the register.
     *
     * ⚠ THIS IS A THIRD SOURCE, AND IT IS THE ONE BELGIUM ACTUALLY USES. The two
     * above read flags a leader ticks while taking attendance. Some churches
     * never tick them: their people fill in the public welcome form instead, so
     * the same Sunday shows names on the Understanding Campaign screen and
     * nothing here. That is what prompted this, and it was not a bug, it was the
     * report looking in a place those churches do not put anything.
     *
     * ⚠ ONLY THE RE-DEDICATING ANSWER COUNTS. The form asks two separate
     * questions: "Are you re-dedicating your life to Christ?" and "Is this your
     * first time attending this church?". Only the first is a decision for
     * Christ; the second is a visitor, who may have been a believer for thirty
     * years. Counting first-timers here would inflate a number the leadership
     * reads as conversions.
     */
    private static function welcomeForm($scope, ?string $from, ?string $to): Collection
    {
        $query = UnderstandingCampaign::query()
            ->where('re_dedicating', true)
            ->with(['stream:id,name', 'allocatedGroup:id,name']);

        /*
         * A submission is tied to a stream, and once someone has been placed it
         * also carries a bacenta. Either being in scope makes it visible, so a
         * country admin still sees submissions that nobody has allocated yet.
         * An empty scope must still match nothing, or a misconfigured admin
         * would quietly see every country.
         */
        if ($scope !== null) {
            $ids = $scope->all();
            $query->where(function ($q) use ($ids) {
                $q->whereIn('stream_id', $ids)->orWhereIn('allocated_group_id', $ids);
            });
        }
        if ($from) {
            $query->whereDate('attended_on', '>=', $from);
        }
        if ($to) {
            $query->whereDate('attended_on', '<=', $to);
        }

        // One row per person, as with the other two: somebody who filled the
        // form on three Sundays is one person to follow up, not three.
        return $query->get()
            ->groupBy(fn ($r) => mb_strtolower(trim($r->first_name.' '.$r->last_name)).'|'.trim((string) $r->phone_number))
            ->map(function ($entries) {
                $latest = $entries->sortByDesc('attended_on')->first();

                return [
                    'name' => trim($latest->first_name.' '.$latest->last_name),
                    'phone' => $latest->phone_number,
                    // The welcome form does not ask for an email.
                    'email' => null,
                    'on_roll' => 'Welcome form',
                    'group' => $latest->allocatedGroup?->name ?? $latest->stream?->name,
                    'first_seen' => self::date($entries->min(fn ($r) => $r->attended_on?->toDateString())),
                    'last_seen' => self::date($entries->max(fn ($r) => $r->attended_on?->toDateString())),
                    'times_marked' => $entries->count(),
                    'nbs_status' => '',
                ];
            })
            ->values();
    }

    /**
     * The admin's country, and the window asked for.
     *
     * Scope is applied to the attendance summary's group, which is the only
     * place a flag is tied to anywhere. A null scope is the group-wide login
     * and stays unrestricted; an empty one restricts to nothing, which must
     * still filter or a misconfigured admin would quietly see every country.
     */
    private static function constrain($query, $scope, ?string $from, ?string $to): void
    {
        if ($scope !== null) {
            $query->whereIn('group_id', $scope);
        }
        if ($from) {
            $query->whereDate('date', '>=', $from);
        }
        if ($to) {
            $query->whereDate('date', '<=', $to);
        }
    }

    private static function date(?string $value): ?string
    {
        return $value ? substr($value, 0, 10) : null;
    }
}
