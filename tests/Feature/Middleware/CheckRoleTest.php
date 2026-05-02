<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\CheckRole;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\BuildsGovernanceFixtures;
use Tests\TestCase;

class CheckRoleTest extends TestCase
{
    use BuildsGovernanceFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedGovernanceTypes();

        Route::middleware(['auth:sanctum', CheckRole::class . ':bishop'])
            ->get('/_test/bishop-only', fn () => response()->json(['ok' => true]));

        Route::middleware(['auth:sanctum', CheckRole::class . ':bishop,governor'])
            ->get('/_test/bishop-or-governor', fn () => response()->json(['ok' => true]));
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/_test/bishop-only')->assertStatus(401);
    }

    public function test_leader_with_correct_role_passes(): void
    {
        $bishop = $this->makeBishop();
        $this->actingAs($bishop, 'sanctum')
            ->getJson('/_test/bishop-only')
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_leader_with_wrong_role_is_rejected(): void
    {
        $constituency = $this->makeConstituency();
        $governor = $this->makeGovernor($constituency);

        $this->actingAs($governor, 'sanctum')
            ->getJson('/_test/bishop-only')
            ->assertStatus(403)
            ->assertJson(['success' => false]);
    }

    public function test_either_role_passes_when_multiple_allowed(): void
    {
        $constituency = $this->makeConstituency();
        $governor = $this->makeGovernor($constituency);

        $this->actingAs($governor, 'sanctum')
            ->getJson('/_test/bishop-or-governor')
            ->assertOk();
    }
}
