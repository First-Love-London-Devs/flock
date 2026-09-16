<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSummary;
use App\Models\Group;
use App\Models\GroupType;
use App\Models\Leader;
use App\Models\LeaderRole;
use App\Models\Member;
use App\Models\RoleDefinition;
use App\Services\DomainScope;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    // ─── Scope helpers ──────────────────────────────────────────────────────

    protected function adminGroupId(Request $request): int
    {
        $role = $request->user()->leaderRoles()
            ->where('is_active', true)
            ->whereNotNull('group_id')
            ->whereHas('roleDefinition', fn ($q) => $q->where('slug', 'admin'))
            ->first();

        abort_if(! $role, response()->json(['success' => false, 'message' => 'Admin group not assigned'], 403));

        return $role->group_id;
    }

    /**
     * Every group this admin may act on: their admin group and everything
     * under it, narrowed to the country the request came in through.
     *
     * These endpoints queried the whole tenant between June and now. That
     * was introduced to fix a real complaint — an admin sitting on a
     * gathering service could not see the wider church — but removing the
     * scope was the wrong lever for it; the right one is to attach the
     * admin role higher up. It only looked harmless because the tenant was
     * a single church, so "the tenant" and "their church" were the same
     * set. They stop being the same set the moment a tenant holds more
     * than one church, and then tenant-wide means every church's admin
     * reads and edits every other church's members.
     */
    protected function scopedGroupIds(Request $request): Collection
    {
        $root = Group::find($this->adminGroupId($request));

        return DomainScope::confine($root ? $root->allGroupIds() : collect());
    }

    protected function scopedBacenta(Request $request, int $id): Group
    {
        $cellGroupTypeId = GroupType::where('slug', 'cell-group')->value('id');

        abort_if(
            ! $this->scopedGroupIds($request)->contains($id),
            response()->json(['success' => false, 'message' => 'Bacenta not in scope'], 403),
        );

        return Group::where('group_type_id', $cellGroupTypeId)->findOrFail($id);
    }

    protected function scopedMember(Request $request, int $id): Member
    {
        $member = Member::with('groups')->findOrFail($id);

        // A member belongs to several groups, so one overlap is enough.
        // A member in no group at all belongs to everyone: see listMembers.
        $inScope = $member->groups->isEmpty()
            || $member->groups->pluck('id')
                ->intersect($this->scopedGroupIds($request))
                ->isNotEmpty();

        abort_if(! $inScope, response()->json(['success' => false, 'message' => 'Member not in scope'], 403));

        return $member;
    }

    // ─── Members ────────────────────────────────────────────────────────────

    public function listMembers(Request $request): JsonResponse
    {
        $search = $request->query('search');
        $perPage = (int) $request->query('per_page', 25);

        /* Members in no group at all stay visible to every admin. createMember
           deliberately allows a member with no bacenta, so scoping purely on
           group membership would let you create someone and then lose them:
           invisible in the list, and unassignable precisely because you can
           no longer see them. There are 22 such records on production today.
           They belong to no country either, so a country subdomain shows them
           too - being seen twice is recoverable, being seen by nobody is not. */
        $scopedIds = $this->scopedGroupIds($request);
        $query = Member::with(['groups:id,name'])
            ->where(fn ($q) => $q
                ->whereHas('groups', fn ($g) => $g->whereIn('groups.id', $scopedIds))
                ->orWhereDoesntHave('groups')
            );

        if ($search) {
            $query->where(fn ($q) => $q
                ->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('phone_number', 'like', "%{$search}%")
            );
        }

        return $this->ok($query->paginate($perPage));
    }

    public function showMember(Request $request, int $id): JsonResponse
    {
        $cellGroupTypeId = GroupType::where('slug', 'cell-group')->value('id');
        $member = $this->scopedMember($request, $id)->load('groups:id,name,group_type_id');

        $data = $member->toArray();
        $data['groups'] = $member->groups->map(fn ($g) => [
            'id' => $g->id,
            'name' => $g->name,
            'is_bacenta' => (int) $g->group_type_id === (int) $cellGroupTypeId,
        ])->values()->all();

        return $this->ok($data);
    }

    public function createMember(Request $request): JsonResponse
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'phone_number' => 'nullable|string|max:30',
            'gender' => 'nullable|string|in:male,female',
            'date_of_birth' => 'nullable|date',
            'member_type' => 'nullable|string|max:50',
            'bacenta_id' => 'nullable|integer',
        ]);

        if (! empty($data['bacenta_id'])) {
            $this->scopedBacenta($request, $data['bacenta_id']);
        }

        $member = Member::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'phone_number' => $data['phone_number'] ?? null,
            'gender' => $data['gender'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'member_type' => $data['member_type'] ?? null,
            'is_active' => true,
            'member_since' => now(),
        ]);

        if (! empty($data['bacenta_id'])) {
            $member->groups()->attach($data['bacenta_id'], [
                'joined_at' => now(),
                'is_primary' => true,
            ]);
        }

        return $this->ok($member->load('groups:id,name'));
    }

    public function updateMember(Request $request, int $id): JsonResponse
    {
        $member = $this->scopedMember($request, $id);

        $data = $request->validate([
            'first_name' => 'sometimes|string|max:100',
            'last_name' => 'sometimes|string|max:100',
            'phone_number' => 'sometimes|nullable|string|max:30',
            'gender' => 'sometimes|nullable|string|in:male,female',
            'date_of_birth' => 'sometimes|nullable|date',
            'member_type' => 'sometimes|nullable|string|max:50',
            'is_active' => 'sometimes|boolean',
        ]);

        $member->update($data);

        return $this->ok($member->fresh()->load('groups:id,name'));
    }

    public function deactivateMember(Request $request, int $id): JsonResponse
    {
        $this->scopedMember($request, $id)->update(['is_active' => false]);

        return $this->ok(['message' => 'Member deactivated']);
    }

    public function updateMemberGroups(Request $request, int $id): JsonResponse
    {
        $member = $this->scopedMember($request, $id);

        $data = $request->validate([
            'bacenta_id' => 'nullable|integer|exists:groups,id',
            'sonta_id' => 'nullable|integer|exists:groups,id',
        ]);

        /* exists:groups,id only proves the group is real, not that it is
           this admin's to move someone into. Without this an admin could
           reassign a member into any group in the tenant by id, which is
           the same hole as reading them. */
        $scopedIds = $this->scopedGroupIds($request);
        foreach (['bacenta_id', 'sonta_id'] as $key) {
            abort_if(
                ! empty($data[$key]) && ! $scopedIds->contains((int) $data[$key]),
                response()->json(['success' => false, 'message' => 'Group not in scope'], 403),
            );
        }

        $cellGroupTypeId = GroupType::where('slug', 'cell-group')->value('id');

        // Detach all current cell-groups and attach the new one (if provided).
        $currentBacentaIds = $member->groups()
            ->where('group_type_id', $cellGroupTypeId)
            ->pluck('groups.id')
            ->all();
        if ($currentBacentaIds) {
            $member->groups()->detach($currentBacentaIds);
        }
        if (! empty($data['bacenta_id'])) {
            $member->groups()->attach($data['bacenta_id'], ['joined_at' => now(), 'is_primary' => true]);
        }

        // Detach all current non-cell-groups and attach the new Sonta (if provided).
        $currentSontaIds = $member->groups()
            ->where('group_type_id', '!=', $cellGroupTypeId)
            ->pluck('groups.id')
            ->all();
        if ($currentSontaIds) {
            $member->groups()->detach($currentSontaIds);
        }
        if (! empty($data['sonta_id'])) {
            $member->groups()->attach($data['sonta_id'], ['joined_at' => now(), 'is_primary' => false]);
        }

        return $this->ok($member->fresh()->load('groups:id,name'));
    }

    // ─── Bacentas ───────────────────────────────────────────────────────────

    private function descendantGroupIds(int $parentId): array
    {
        $ids = [];
        $queue = [$parentId];
        while ($queue) {
            $current = array_shift($queue);
            $children = Group::where('parent_id', $current)->pluck('id')->all();
            foreach ($children as $child) {
                $ids[] = $child;
                $queue[] = $child;
            }
        }

        return $ids;
    }

    public function listSontas(Request $request): JsonResponse
    {
        $adminGroupId = $this->adminGroupId($request);
        $subtreeIds = DomainScope::confine(collect($this->descendantGroupIds($adminGroupId)));
        $cellGroupTypeId = GroupType::where('slug', 'cell-group')->value('id');
        $search = $request->query('search');

        $query = Group::whereIn('id', $subtreeIds)
            ->where('group_type_id', '!=', $cellGroupTypeId)
            ->where('is_active', true)
            ->withCount('members');

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        return $this->ok($query->get());
    }

    public function listBacentas(Request $request): JsonResponse
    {
        $adminGroupId = $this->adminGroupId($request);
        $subtreeIds = DomainScope::confine(collect($this->descendantGroupIds($adminGroupId)));
        $cellGroupTypeId = GroupType::where('slug', 'cell-group')->value('id');
        $search = $request->query('search');

        $query = Group::whereIn('id', $subtreeIds)
            ->where('group_type_id', $cellGroupTypeId)
            ->where('is_active', true)
            ->withCount('members');

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        return $this->ok($query->get());
    }

    public function showBacenta(Request $request, int $id): JsonResponse
    {
        return $this->ok($this->scopedBacenta($request, $id)->loadCount('members'));
    }

    public function createBacenta(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => 'required|string|max:150']);

        $parentId = $this->adminGroupId($request);
        $cellGroupTypeId = GroupType::where('slug', 'cell-group')->value('id');

        $bacenta = Group::create([
            'name' => $data['name'],
            'parent_id' => $parentId,
            'group_type_id' => $cellGroupTypeId,
            'is_active' => true,
        ]);

        return $this->ok($bacenta->loadCount('members'));
    }

    public function updateBacenta(Request $request, int $id): JsonResponse
    {
        $bacenta = $this->scopedBacenta($request, $id);

        $data = $request->validate([
            'name' => 'sometimes|string|max:150',
            'is_active' => 'sometimes|boolean',
        ]);

        $bacenta->update($data);

        return $this->ok($bacenta->fresh()->loadCount('members'));
    }

    public function deactivateBacenta(Request $request, int $id): JsonResponse
    {
        $this->scopedBacenta($request, $id)->update(['is_active' => false]);

        return $this->ok(['message' => 'Bacenta deactivated']);
    }

    // ─── Leadership roles ─────────────────────────────────────────────────────

    /**
     * The only roles an admin may hand out. Appointing overseers is above this
     * role — an admin makes the on-the-ground leaders (a Bacenta's cell leader,
     * a ministry's leader), and nothing higher.
     *
     * @return array<int, string>
     */
    protected function grantableRoleSlugs(): array
    {
        return ['cell-leader', 'ministry-leader'];
    }

    /** The grantable roles as pickable rows for the app. */
    protected function grantableRoles(): array
    {
        return RoleDefinition::whereIn('slug', $this->grantableRoleSlugs())
            ->where('is_active', true)
            ->get(['id', 'name', 'slug'])
            ->all();
    }

    /**
     * A member's leadership, as the app needs it to draw the section: whether
     * they already have a login, their live roles, and which roles this admin
     * can add. Only the grantable roles are marked removable — a member who is
     * also a governor shows that role, but this admin cannot strip it.
     */
    public function memberLeadership(Request $request, int $id): JsonResponse
    {
        $member = $this->scopedMember($request, $id);
        $leader = Leader::where('member_id', $member->id)->first();

        $roles = $leader
            ? $leader->leaderRoles()
                ->where('is_active', true)
                ->with(['roleDefinition:id,name,slug', 'group:id,name'])
                ->get()
                ->map(fn ($lr) => [
                    'id' => $lr->id,
                    'role_slug' => $lr->roleDefinition?->slug,
                    'role_name' => $lr->roleDefinition?->name,
                    'group_id' => $lr->group_id,
                    'group_name' => $lr->group?->name,
                    'removable' => in_array($lr->roleDefinition?->slug, $this->grantableRoleSlugs(), true),
                ])->values()->all()
            : [];

        return $this->ok([
            'has_login' => (bool) $leader,
            'username' => $leader?->username,
            'roles' => $roles,
            'grantable_roles' => $this->grantableRoles(),
        ]);
    }

    /**
     * Make a member a cell or ministry leader for one of the admin's groups.
     *
     * The first role a member is given turns them into a leader with a login;
     * the credentials come back once, in the response, for the admin to pass on
     * and are never shown again. Re-granting the same role on the same group is
     * idempotent — it does not stack, and it revives a role that was removed.
     */
    public function assignLeadershipRole(Request $request, int $id): JsonResponse
    {
        $member = $this->scopedMember($request, $id);

        $validated = $request->validate([
            'role_slug' => ['required', Rule::in($this->grantableRoleSlugs())],
            'group_id' => 'required|integer|exists:groups,id',
        ]);

        // exists:groups,id proves the group is real, not that it is this admin's.
        abort_if(
            ! $this->scopedGroupIds($request)->contains((int) $validated['group_id']),
            response()->json(['success' => false, 'message' => 'Group not in scope'], 403),
        );

        $roleDef = RoleDefinition::where('slug', $validated['role_slug'])
            ->where('is_active', true)
            ->first();
        abort_if(! $roleDef, response()->json(['success' => false, 'message' => 'Role not available'], 422));

        $credentials = null;
        $leader = Leader::where('member_id', $member->id)->first();
        if (! $leader) {
            $username = $this->generateUsername($member);
            $password = $this->generatePassword();
            $leader = Leader::create([
                'member_id' => $member->id,
                'username' => $username,
                'password' => $password, // hashed by the model's mutator
                'is_active' => true,
            ]);
            $credentials = ['username' => $username, 'password' => $password];
        }

        $leaderRole = LeaderRole::firstOrCreate(
            [
                'leader_id' => $leader->id,
                'role_definition_id' => $roleDef->id,
                'group_id' => (int) $validated['group_id'],
            ],
            [
                'assigned_at' => now(),
                'is_active' => true,
            ],
        );
        if (! $leaderRole->is_active) {
            $leaderRole->update(['is_active' => true, 'assigned_at' => now()]);
        }

        return $this->ok([
            'role' => [
                'id' => $leaderRole->id,
                'role_slug' => $roleDef->slug,
                'role_name' => $roleDef->name,
                'group_id' => $leaderRole->group_id,
                'removable' => true,
            ],
            // Present only when a login was just created for this member.
            'credentials' => $credentials,
        ]);
    }

    /**
     * Take back a role this admin granted. Only the grantable roles can be
     * removed here, and only when the role's group is in scope — an admin
     * cannot use this to strip a governor or reach outside their patch.
     */
    public function removeLeadershipRole(Request $request, int $id, int $leaderRoleId): JsonResponse
    {
        $member = $this->scopedMember($request, $id);
        $leader = Leader::where('member_id', $member->id)->firstOrFail();

        $leaderRole = LeaderRole::where('leader_id', $leader->id)
            ->with('roleDefinition:id,slug')
            ->findOrFail($leaderRoleId);

        abort_if(
            ! in_array($leaderRole->roleDefinition?->slug, $this->grantableRoleSlugs(), true),
            response()->json(['success' => false, 'message' => 'This role cannot be removed here'], 403),
        );

        abort_if(
            $leaderRole->group_id && ! $this->scopedGroupIds($request)->contains((int) $leaderRole->group_id),
            response()->json(['success' => false, 'message' => 'Group not in scope'], 403),
        );

        $leaderRole->delete();

        return $this->ok(['message' => 'Role removed']);
    }

    protected function generateUsername(Member $member): string
    {
        $base = Str::slug(trim($member->first_name.' '.$member->last_name), '.');
        if ($base === '') {
            $base = 'leader';
        }

        $username = $base;
        $n = 1;
        while (Leader::where('username', $username)->exists()) {
            $n++;
            $username = $base.$n;
        }

        return $username;
    }

    protected function generatePassword(): string
    {
        // Readable enough to read down a phone: a capital, some lowercase, digits.
        return Str::ucfirst(Str::lower(Str::random(5))).random_int(1000, 9999);
    }

    // ─── Submissions ──────────────────────────────────────────────────────────

    /**
     * Which of this admin's Bacentas have submitted this week, Sunday and
     * midweek, with the not-yet list. Same computation the governor dashboard
     * uses (ConstituencyAnalytics::groups), scoped to the admin's subtree.
     */
    public function submissions(Request $request): JsonResponse
    {
        $adminGroupId = $this->adminGroupId($request);
        $subtreeIds = DomainScope::confine(collect($this->descendantGroupIds($adminGroupId)));
        $cellGroupTypeId = GroupType::where('slug', 'cell-group')->value('id');

        [$weekStart, $weekEnd] = $this->currentWeekBounds();

        $bacentas = Group::whereIn('id', $subtreeIds)
            ->where('group_type_id', $cellGroupTypeId)
            ->where('is_active', true)
            ->with(['leader.member:id,first_name,last_name'])
            ->withCount('members')
            ->orderBy('name')
            ->get();

        $thisWeek = AttendanceSummary::whereIn('group_id', $bacentas->pluck('id'))
            ->whereBetween('date', [$weekStart, $weekEnd])
            ->get()
            ->groupBy('group_id');

        $rows = $bacentas->map(function ($g) use ($thisWeek) {
            $summaries = $thisWeek->get($g->id, collect());
            $sunday = $summaries->first(fn ($r) => Carbon::parse($r->date)->isSunday());
            $midweek = $summaries->first(fn ($r) => ! Carbon::parse($r->date)->isSunday());
            $leaderMember = $g->leader?->member;

            return [
                'id' => $g->id,
                'name' => $g->name,
                'members_count' => $g->members_count,
                'leader_name' => $leaderMember ? trim($leaderMember->first_name.' '.$leaderMember->last_name) : null,
                'sunday_submitted' => (bool) $sunday,
                'midweek_submitted' => (bool) $midweek,
            ];
        });

        return $this->ok([
            'week_start' => Carbon::parse($weekStart)->toDateString(),
            'total' => $rows->count(),
            'sunday_submitted' => $rows->where('sunday_submitted', true)->count(),
            'midweek_submitted' => $rows->where('midweek_submitted', true)->count(),
            'bacentas' => $rows->values()->all(),
        ]);
    }

    /** This week, Monday 00:00 to Sunday end-of-day (mirrors ConstituencyAnalytics). */
    protected function currentWeekBounds(): array
    {
        $start = Carbon::now()->startOfWeek();

        return [$start->toDateString(), $start->copy()->endOfWeek()->endOfDay()->toDateTimeString()];
    }

    protected function ok(mixed $data): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data]);
    }
}
