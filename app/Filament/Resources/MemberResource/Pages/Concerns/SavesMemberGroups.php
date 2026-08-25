<?php

namespace App\Filament\Resources\MemberResource\Pages\Concerns;

use App\Models\Group;
use App\Models\User;

/**
 * Persist the Bacenta picker, and make sure a new member lands somewhere
 * their author can still see.
 *
 * Two bugs meet here.
 *
 * The picker is `dehydrated(false)`, because bacenta_groups is not a column,
 * and nothing anywhere ever synced it. So choosing a bacenta on the member
 * form has never done anything, on create or on edit. That is why assigning a
 * country's roll meant a bulk action rather than editing people one at a time.
 *
 * And a member is only visible to a country admin through the groups they
 * belong to (see MemberResource::applyGroupScope). A brand-new member belongs
 * to no group, so the moment a Switzerland admin created one it vanished from
 * their own panel — Filament redirects to the edit page, that page resolves
 * the record through the scoped query, finds nothing, and the admin sees an
 * error on a member that was in fact saved.
 *
 * So: save what they picked, and if they picked nothing, fall back to the
 * admin's own scope group as a holding position rather than letting the
 * record disappear. The group-wide admin is unscoped and needs no fallback.
 */
trait SavesMemberGroups
{
    protected function syncMemberGroups(): void
    {
        $member = $this->record;

        if (! $member) {
            return;
        }

        $chosen = collect($this->data['bacenta_groups'] ?? [])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($chosen !== []) {
            /* syncWithoutDetaching, not sync: a member can also sit in a
               basonta, which this picker deliberately does not list, and a
               plain sync would silently drop it. */
            $member->groups()->syncWithoutDetaching(
                collect($chosen)->mapWithKeys(fn ($id) => [$id => ['joined_at' => now()->toDateString()]])->all()
            );

            // Bacentas the picker offered and the user cleared.
            $offered = self::pickableGroupIds();
            $remove = array_diff($member->groups()->pluck('groups.id')->intersect($offered)->all(), $chosen);
            if ($remove !== []) {
                $member->groups()->detach($remove);
            }
        }

        $this->ensureVisibleToItsAuthor($member);
    }

    /**
     * A record its author cannot see reads as a failure even though it saved.
     */
    private function ensureVisibleToItsAuthor($member): void
    {
        $scope = User::currentScopeIds();

        if ($scope === null) {
            return; // Unscoped admin sees everything anyway.
        }

        if ($member->groups()->whereIn('groups.id', $scope)->exists()) {
            return;
        }

        $home = auth()->user()?->scope_group_id;

        if ($home) {
            $member->groups()->syncWithoutDetaching([
                $home => ['joined_at' => now()->toDateString()],
            ]);
        }
    }

    /** The bacentas this form's picker offers, so clearing one can be honoured. */
    private static function pickableGroupIds(): array
    {
        return Group::query()
            ->whereHas('groupType', fn ($q) => $q->where('tracks_attendance', true)
                ->whereRaw('LOWER(slug) != ?', ['basonta']))
            ->pluck('id')
            ->all();
    }
}
