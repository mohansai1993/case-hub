<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ProfilePhotoTest extends ApiTestCase
{
    private const UPDATE = '/api/v1/profile/photo';

    public function test_guests_cannot_update_the_photo(): void
    {
        $this->postJson(self::UPDATE, ['photo' => UploadedFile::fake()->image('a.jpg')])->assertStatus(401);
    }

    public function test_a_client_can_set_a_profile_photo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['image' => null]);

        $response = $this->post(self::UPDATE, ['photo' => UploadedFile::fake()->image('headshot.jpg', 400, 400)->size(800)], array_merge($this->bearer($user), ['Accept' => 'application/json']))
            ->assertOk();

        $user->refresh();
        $this->assertStringStartsWith('profile-photos/', $user->image);
        Storage::disk('public')->assertExists($user->image);
        $response->assertJsonPath('data.image_url', $user->imageUrl());
    }

    public function test_a_lawyer_can_also_update_their_photo(): void
    {
        Storage::fake('public');
        $lawyer = User::factory()->lawyer()->create(['image' => null]);

        $this->post(self::UPDATE, ['photo' => UploadedFile::fake()->image('a.jpg')], array_merge($this->bearer($lawyer), ['Accept' => 'application/json']))
            ->assertOk();

        $this->assertNotNull($lawyer->fresh()->image);
    }

    public function test_updating_the_photo_replaces_and_deletes_the_old_one(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $oldPath = UploadedFile::fake()->image('old.jpg')->store('profile-photos', 'public');
        $user->update(['image' => $oldPath]);

        $this->post(self::UPDATE, ['photo' => UploadedFile::fake()->image('new.jpg')], array_merge($this->bearer($user), ['Accept' => 'application/json']))
            ->assertOk();

        Storage::disk('public')->assertMissing($oldPath);
        $this->assertNotSame($oldPath, $user->fresh()->image);
    }

    public function test_photo_must_be_a_jpg_or_png_up_to_5mb(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $headers = array_merge($this->bearer($user), ['Accept' => 'application/json']);

        $this->post(self::UPDATE, ['photo' => UploadedFile::fake()->image('big.jpg')->size(5121)], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('photo');

        $this->post(self::UPDATE, ['photo' => UploadedFile::fake()->image('anim.gif')], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('photo');

        $this->post(self::UPDATE, ['photo' => UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf')], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('photo');
    }

    public function test_photo_is_required(): void
    {
        $user = User::factory()->create();

        $this->postJson(self::UPDATE, [], $this->bearer($user))
            ->assertUnprocessable()->assertJsonValidationErrors('photo');
    }
}
