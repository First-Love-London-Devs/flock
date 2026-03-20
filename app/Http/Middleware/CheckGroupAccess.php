<?php

namespace App\Http\Middleware;

use App\Models\Group;
use App\Models\Leader;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckGroupAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $groupId = $request->route('id') ?? $request->route('groupId') ?? $request->route('group') ?? $request->input('group_id');

        if (!$groupId) {
            return $next($request);
        }

        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        // Find leader for this user
        $leader = Leader::where('user_id', $user->id)->first();
        if (!$leader) {
            // Check if user is super_admin — they bypass group access
            if ($user->user_type === 'super_admin') {
                return $next($request);
            }
            return response()->json(['success' => false, 'message' => 'No leader profile found'], 403);
        }

        // Get leader's scoped group IDs from active roles
        $scopedGroupIds = $leader->leaderRoles()
            ->where('is_active', true)
            ->pluck('group_id')
            ->filter() // remove nulls
            ->toArray();

        // Check if leader has any global role (group_id is null)
        $hasGlobalRole = $leader->leaderRoles()
            ->where('is_active', true)
            ->whereNull('group_id')
            ->exists();

        if ($hasGlobalRole) {
            return $next($request);
        }

        // Check if the requested group is in the leader's scope (or is a descendant)
        $group = Group::find($groupId);
        if (!$group) {
            return $next($request); // Let the controller handle 404
        }

        // Check direct match
        if (in_array($groupId, $scopedGroupIds)) {
            return $next($request);
        }

        // Check if any scoped group is an ancestor of the requested group
        $ancestorIds = $group->ancestors()->pluck('id')->toArray();
        if (array_intersect($scopedGroupIds, $ancestorIds)) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'You do not have access to this group',
        ], 403);
    }
}
