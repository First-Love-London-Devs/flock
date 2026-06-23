<?php

namespace Tests\Feature\UnderstandingCampaign;

use App\Models\UnderstandingCampaign;
use Tests\TestCase;

class UnderstandingCampaignModelTest extends TestCase
{
    private function validAttributes(array $overrides = []): array
    {
        return array_merge([
            'attended_on' => '2026-06-22',
            'first_name' => 'Jan',
            'last_name' => 'Janssen',
            'street_name' => 'Kerkstraat 1',
            'postal_code' => '3000',
            'phone_number' => '+32470000000',
            're_dedicating' => true,
            'first_time' => true,
            'who_invited' => 'Piet',
        ], $overrides);
    }

    public function test_it_persists_a_submission_with_casts(): void
    {
        $uc = UnderstandingCampaign::create($this->validAttributes());

        $fresh = $uc->fresh();
        $this->assertTrue($fresh->re_dedicating);
        $this->assertTrue($fresh->first_time);
        $this->assertSame('2026-06-22', $fresh->attended_on->toDateString());
        $this->assertNull($fresh->allocated_group_id);
        $this->assertDatabaseHas('understanding_campaigns', [
            'first_name' => 'Jan',
            'last_name' => 'Janssen',
            'who_invited' => 'Piet',
        ]);
    }
}
