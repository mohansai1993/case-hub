<?php

namespace Tests\Feature\Api;

use App\Enums\UserStatus;
use App\Enums\VerificationStatus;
use App\Models\PracticeArea;
use App\Models\User;

class LawyerDirectoryTest extends ApiTestCase
{
    private function verifiedLawyer(string $name, ?PracticeArea $area = null): User
    {
        $lawyer = User::factory()->lawyer(VerificationStatus::Verified)->create(['name' => $name]);

        if ($area) {
            $lawyer->practiceAreas()->attach($area->id);
        }

        return $lawyer;
    }

    public function test_directory_only_lists_verified_active_lawyers(): void
    {
        $client = User::factory()->create();
        $matched = $this->verifiedLawyer('Adv. Sarah Jenkins');

        $unverified = User::factory()->lawyer(VerificationStatus::Pending)->create();
        $suspended = $this->verifiedLawyer('Suspended Lawyer');
        $suspended->forceFill(['status' => UserStatus::Suspended])->save();

        $this->getJson('/api/v1/lawyers', $this->bearer($client))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matched->user_id);
    }

    public function test_directory_can_be_searched_by_name(): void
    {
        $client = User::factory()->create();
        $this->verifiedLawyer('Adv. Sarah Jenkins');
        $this->verifiedLawyer('Adv. James Wilson');

        $this->getJson('/api/v1/lawyers?q=Sarah', $this->bearer($client))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Adv. Sarah Jenkins');
    }

    public function test_directory_can_be_filtered_by_practice_area(): void
    {
        $client = User::factory()->create();
        $area = PracticeArea::create(['slug' => 'ipr', 'name' => 'IP Rights', 'is_active' => true]);
        $otherArea = PracticeArea::create(['slug' => 'tax', 'name' => 'Tax', 'is_active' => true]);

        $matched = $this->verifiedLawyer('IP Lawyer', $area);
        $this->verifiedLawyer('Tax Lawyer', $otherArea);

        $this->getJson("/api/v1/lawyers?practice_area_id={$area->id}", $this->bearer($client))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matched->user_id);
    }

    public function test_only_clients_can_browse_the_lawyer_directory(): void
    {
        $lawyer = User::factory()->lawyer(VerificationStatus::Verified)->create();

        $this->getJson('/api/v1/lawyers', $this->bearer($lawyer))->assertForbidden();
    }
}
