<?php

namespace App\Support;

use App\Models\Attendance;
use App\Models\Group;
use App\Models\Member;
use App\Models\NonMemberAttendance;
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

        return self::members($scope, $from, $to)
            ->merge(self::nonMembers($scope, $from, $to))
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
