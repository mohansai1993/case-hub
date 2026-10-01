<?php

namespace Tests\Feature\Api;

use App\Enums\VerificationStatus;
use App\Models\PracticeArea;
use App\Models\User;

class LawyerProfileTest extends ApiTestCase
{
    private const UPDATE = '/api/v1/profile/lawyer';

    private function payload(array $override = []): array
    {
        return array_merge([
            'location' => 'New Delhi, India',
            'years_of_experience' => 8,
            'practice_areas' => PracticeArea::whereIn('slug', ['severance', 'compliance'])->pluck('id')->all(),
            'bio' => 'Specializing in corporate governance.',
        ], $override);
    }

    public function test_guests_cannot_update_the_lawyer_profile(): void
    {
        $this->putJson(self::UPDATE, $this->payload())->assertStatus(401);
    }

    public function test_clients_cannot_use_this_endpoint(): void
    {
        $client = User::factory()->create();

        $this->putJson(self::UPDATE, $this->payload(), $this->bearer($client))
            ->assertForbidden()
            ->assertJsonPath('message', 'Only lawyers have a practice profile to update.');
    }

    public function test_a_lawyer_can_update_location_experience_practice_areas_and_bio(): void
    {
        $lawyer = User::factory()->lawyer()->create();
        $severance = PracticeArea::firstWhere('slug', 'severance');
        $lawyer->practiceAreas()->sync([$severance->id]);

        $compliance = PracticeArea::firstWhere('slug', 'compliance');
        $litigation = PracticeArea::firstWhere('slug', 'litigation')
            ?? PracticeArea::create(['slug' => 'litigation', 'name' => 'Litigation', 'is_active' => true]);

        $response = $this->putJson(self::UPDATE, [
            'location' => 'Mumbai, India',
            'years_of_experience' => 12,
            'practice_areas' => [$compliance->id, $litigation->id],
            'bio' => 'Updated bio text.',
        ], $this->bearer($lawyer))->assertOk();

        $profile = $lawyer->fresh()->lawyerProfile;
        $this->assertSame('Mumbai, India', $profile->location);
        $this->assertSame(12, $profile->years_of_experience);
        $this->assertSame('Updated bio text.', $profile->bio);

        $this->assertEqualsCanonicalizing(
            [$compliance->id, $litigation->id],
            $lawyer->practiceAreas()->pluck('practice_areas.id')->all(),
        );

        $response->assertJsonPath('data.user.lawyer.location', 'Mumbai, India');
        $response->assertJsonPath('data.user.lawyer.years_of_experience', 12);
        $response->assertJsonPath('data.user.lawyer.bio', 'Updated bio text.');
    }

    public function test_bio_is_optional(): void
    {
        $lawyer = User::factory()->lawyer()->create();

        $this->putJson(self::UPDATE, $this->payload(['bio' => null]), $this->bearer($lawyer))->assertOk();

        $this->assertNull($lawyer->fresh()->lawyerProfile->bio);
    }

    public function test_at_least_one_practice_area_is_required(): void
    {
        $lawyer = User::factory()->lawyer()->create();

        $this->putJson(self::UPDATE, $this->payload(['practice_areas' => []]), $this->bearer($lawyer))
            ->assertUnprocessable()->assertJsonValidationErrors('practice_areas');
    }

    public function test_an_inactive_practice_area_is_rejected(): void
    {
        $lawyer = User::factory()->lawyer()->create();
        $inactive = PracticeArea::create(['slug' => 'retired-area', 'name' => 'Retired Area', 'is_active' => false]);

        $this->putJson(self::UPDATE, $this->payload(['practice_areas' => [$inactive->id]]), $this->bearer($lawyer))
            ->assertUnprocessable()->assertJsonValidationErrors('practice_areas.0');
    }

    public function test_years_of_experience_must_be_a_realistic_number(): void
    {
        $lawyer = User::factory()->lawyer()->create();

        $this->putJson(self::UPDATE, $this->payload(['years_of_experience' => -1]), $this->bearer($lawyer))
            ->assertUnprocessable()->assertJsonValidationErrors('years_of_experience');

        $this->putJson(self::UPDATE, $this->payload(['years_of_experience' => 71]), $this->bearer($lawyer))
            ->assertUnprocessable()->assertJsonValidationErrors('years_of_experience');
    }

    public function test_updating_the_profile_does_not_touch_verification_status(): void
    {
        $lawyer = User::factory()->lawyer(VerificationStatus::Verified)->create();

        $this->putJson(self::UPDATE, $this->payload(), $this->bearer($lawyer))->assertOk();

        $this->assertSame(VerificationStatus::Verified, $lawyer->fresh()->lawyerProfile->verification_status);
    }
}
