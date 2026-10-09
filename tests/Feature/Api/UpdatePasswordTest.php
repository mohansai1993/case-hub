<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UpdatePasswordTest extends ApiTestCase
{
    private const UPDATE = '/api/v1/auth/password';
    private const NEW_PASSWORD = 'Brand-New-Pass9';

    private function update(array $override = [], array $headers = [])
    {
        return $this->putJson(self::UPDATE, array_merge([
            'current_password' => self::PASSWORD,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ], $override), $headers);
    }

    public function test_a_client_can_update_their_password(): void
    {
        $user = User::factory()->create();

        $this->update([], $this->bearer($user))->assertOk()->assertJsonPath('message', 'Password updated successfully.');

        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->fresh()->password));
    }

    public function test_a_lawyer_can_update_their_password(): void
    {
        $lawyer = User::factory()->lawyer()->create();

        $this->update([], $this->bearer($lawyer))->assertOk();

        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $lawyer->fresh()->password));
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->update(['current_password' => 'WrongPassword@1'], $this->bearer($user))
            ->assertUnprocessable()->assertJsonValidationErrors('current_password');

        $this->assertTrue(Hash::check(self::PASSWORD, $user->fresh()->password));
    }

    public function test_weak_new_password_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->update(['password' => 'weak', 'password_confirmation' => 'weak'], $this->bearer($user))
            ->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_mismatched_confirmation_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->update(['password_confirmation' => 'SomethingElse9'], $this->bearer($user))
            ->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_cannot_reuse_the_current_password(): void
    {
        $user = User::factory()->create();

        $this->update([
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ], $this->bearer($user))->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_cannot_reuse_one_of_the_last_5_passwords(): void
    {
        $user = User::factory()->create();
        $token = $this->bearer($user);

        $passwords = [self::PASSWORD, 'Second-Pass9', 'Third-Pass99', 'Fourth-Pass9', 'Fifth-Pass99'];

        for ($i = 1; $i < count($passwords); $i++) {
            $this->putJson(self::UPDATE, [
                'current_password' => $passwords[$i - 1],
                'password' => $passwords[$i],
                'password_confirmation' => $passwords[$i],
            ], $token)->assertOk();
        }

        // 5 distinct passwords have now been used (the original + 4 changes) - reusing any of them is blocked.
        $this->putJson(self::UPDATE, [
            'current_password' => end($passwords),
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
        ], $token)->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_other_devices_are_signed_out_but_this_device_stays_signed_in(): void
    {
        $user = User::factory()->create();
        $thisDevice = $this->bearer($user);
        $otherDevice = $this->bearer($user);

        $this->update([], $thisDevice)->assertOk();

        $this->app['auth']->forgetGuards(); // the test app would otherwise reuse the resolved user
        $this->getJson('/api/v1/auth/me', $thisDevice)->assertOk();

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/auth/me', $otherDevice)->assertStatus(401);
    }

    public function test_guests_cannot_update_password(): void
    {
        $this->putJson(self::UPDATE, [
            'current_password' => self::PASSWORD,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])->assertStatus(401);
    }
}
