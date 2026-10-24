<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NonMember;
use App\Services\DomainScope;
use App\Services\LeaderScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NonMemberController extends Controller
{
    public function __construct(
        protected LeaderScopeService $scope,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = NonMember::active();

        // Confine to the caller's groups, the same way members are scoped, so
        // the list can't reach non-members in other churches. A tenant-wide
        // super admin with no domain lens sees everything (incl. unassigned).
        if (! ($this->scope->isSuperAdmin() && DomainScope::groupIds() === null)) {
            $query->whereIn('group_id', $this->scope->getAccessibleGroupIds());
        }

        if ($request->has('group_id')) {
            $query->where('group_id', $request->group_id);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        $nonMembers = $query->orderBy('first_name')->get();

        return response()->json([
            'success' => true,
            'data' => $nonMembers,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone_number' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'gender' => 'nullable|in:male,female,other',
            'group_id' => 'nullable|exists:groups,id',
            'notes' => 'nullable|string',
        ]);

        if (! empty($validated['group_id'])) {
            abort_unless(
                $this->scope->canAccessGroup((int) $validated['group_id']),
                403,
                'That group is outside your scope.'
            );
        }

        $nonMember = NonMember::create($validated);

        return response()->json([
            'success' => true,
            'data' => $nonMember,
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $nonMember = NonMember::findOrFail($id);
        $this->authorizeNonMemberAccess($nonMember);

        return response()->json([
            'success' => true,
            'data' => $nonMember,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $nonMember = NonMember::findOrFail($id);
        $this->authorizeNonMemberAccess($nonMember);

        $validated = $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'phone_number' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'gender' => 'nullable|in:male,female,other',
            'group_id' => 'nullable|exists:groups,id',
            'notes' => 'nullable|string',
        ]);

        if (! empty($validated['group_id'])) {
            abort_unless(
                $this->scope->canAccessGroup((int) $validated['group_id']),
                403,
                'That group is outside your scope.'
            );
        }

        $nonMember->update($validated);

        return response()->json([
            'success' => true,
            'data' => $nonMember,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $nonMember = NonMember::findOrFail($id);
        $this->authorizeNonMemberAccess($nonMember);
        $nonMember->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Non-member deactivated.',
        ]);
    }

    /**
     * Abort 403 if this non-member sits in a group (or none) the caller can't
     * reach. An unassigned non-member (no group) is visible only to a
     * tenant-wide super admin.
     */
    private function authorizeNonMemberAccess(NonMember $nonMember): void
    {
        if ($nonMember->group_id === null) {
            abort_unless($this->scope->isSuperAdmin(), 403, 'This record is outside your scope.');

            return;
        }

        abort_unless(
            $this->scope->canAccessGroup((int) $nonMember->group_id),
            403,
            'This record is outside your scope.'
        );
    }
}
