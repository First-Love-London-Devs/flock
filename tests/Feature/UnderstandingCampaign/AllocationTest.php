<?php

namespace Tests\Feature\UnderstandingCampaign;

use App\Filament\Resources\UnderstandingCampaignResource;
use App\Models\UnderstandingCampaign;
use Tests\Concerns\BuildsGovernanceFixtures;
use Tests\TestCase;

class AllocationTest extends TestCase
{
    use BuildsGovernanceFixtures;

    private function makeSubmission(): UnderstandingCampaign
    {
        return UnderstandingCampaign::create([
            'attended_on' => '2026-06-22',
            'first_name' => 'Jan',
            'last_name' => 'Janssen',
            'street_name' => 'Kerkstraat 1',
            'postal_code' => '3000',
            'phone_number' => '+32470000000',
            're_dedicating' => false,
            'first_time' => true,
            'who_invited' => 'Piet',
        ]);
    }

    public function test_submission_can_be_allocated_to_a_bacenta(): void
    {
        $this->seedGovernanceTypes();
        $constituency = $this->makeConstituency();
        $bacenta = $this->makeCellGroup($constituency);

        $uc = $this->makeSubmission();
        $uc->update(['allocated_group_id' => $bacenta->id]);

        $this->assertSame($bacenta->id, $uc->fresh()->allocated_group_id);
        $this->assertSame($bacenta->name, $uc->fresh()->allocatedGroup->name);
    }

    public function test_resource_registers_list_and_edit_pages(): void
    {
        $pages = UnderstandingCampaignResource::getPages();

        $this->assertArrayHasKey('index', $pages);
        $this->assertArrayHasKey('edit', $pages);
    }
}
