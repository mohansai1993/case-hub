<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Notifications\VerificationCode;
use Illuminate\Support\Facades\Notification;

class OtpTest extends ApiTestCase
{
    private const VERIFY = '/api/v1/auth/otp/verify';
    private const RESEND = '/api/v1/auth/otp/resend';

    private function pendingUser(): User
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/register/client', $this->clientPayload())->assertCreated();

        return User::firstWhere('email', 'rahul@example.com');
    }

    public function test_correct_otp_verifies_the_account_and_signs_the_user_in(): void
    {
        $user = $this->pendingUser();

        $response = $this->postJson(self::VERIFY, ['mobile' => '9876543210', 'otp' => $this->sentCode($user), 'device_name' => 'Pixel 8'])
            ->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.mobile_verified', true);

        $this->assertNotNull($user->fresh()->mobile_verified_at);
        $this->assertDatabaseCount('user_otps', 0);
        $this->assertSame('Pixel 8', $user->tokens()->first()->name);
        $this->assertSame(['client'], $user->tokens()->first()->abilities);

        $this->getJson('/api/v1/auth/me', ['Authorization' => 'Bearer ' . $response->json('data.token')])
            ->assertOk()->assertJsonPath('data.user.email', 'rahul@example.com');
    }

    public function test_mobile_can_be_sent_in_any_common_format(): void
    {
        $user = $this->pendingUser();

        $this->postJson(self::VERIFY, ['mobile' => '+91 98765-43210', 'otp' => $this->sentCode($user)])->assertOk();
    }

    public function test_wrong_otp_is_rejected_without_saying_why(): void
    {
        $user = $this->pendingUser();

        $this->postJson(self::VERIFY, ['mobile' => '9876543210', 'otp' => $this->wrongCode($this->sentCode($user))])
            ->assertStatus(422)
            ->assertJsonPath('code', 'invalid_otp');

        $this->assertNull($user->fresh()->mobile_verified_at);
    }

    public function test_unknown_number_gets_the_same_answer_as_a_wrong_code(): void
    {
        $wrongForKnown = $this->postJson(self::VERIFY, ['mobile' => '9876543210', 'otp' => '1234']);
        $unknown = $this->postJson(self::VERIFY, ['mobile' => '9123456780', 'otp' => '1234']);

        $this->assertSame($wrongForKnown->status(), $unknown->status());
        $this->assertSame($wrongForKnown->json(), $unknown->json());
    }

    public function test_otp_is_burned_after_too_many_wrong_guesses(): void
    {
        $user = $this->pendingUser();
        $code = $this->sentCode($user);

        for ($i = 0; $i < config('otp.app.max_attempts'); $i++) {
            $this->postJson(self::VERIFY, ['mobile' => '9876543210', 'otp' => $this->wrongCode($code)])->assertStatus(422);
        }

        // Even the right code no longer works: a 4-digit code must not be brute-forceable.
        $this->postJson(self::VERIFY, ['mobile' => '9876543210', 'otp' => $code])->assertStatus(422);
        $this->assertNull($user->fresh()->mobile_verified_at);
    }

    public function test_expired_otp_is_rejected(): void
    {
        $user = $this->pendingUser();
        $code = $this->sentCode($user);

        $this->travel(config('otp.app.ttl') + 1)->minutes();

        $this->postJson(self::VERIFY, ['mobile' => '9876543210', 'otp' => $code])->assertStatus(422);
    }

    public function test_otp_must_be_exactly_four_digits(): void
    {
        $this->pendingUser();

        foreach (['123', '12345', 'abcd', '12 4', ''] as $bad) {
            $this->postJson(self::VERIFY, ['mobile' => '9876543210', 'otp' => $bad])
                ->assertStatus(422)->assertJsonValidationErrors('otp');
        }
    }

    public function test_an_otp_is_single_use(): void
    {
        $user = $this->pendingUser();
        $code = $this->sentCode($user);

        $this->postJson(self::VERIFY, ['mobile' => '9876543210', 'otp' => $code])->assertOk();
        $this->postJson(self::VERIFY, ['mobile' => '9876543210', 'otp' => $code])->assertStatus(422);
    }

    public function test_resend_respects_the_cooldown_then_sends_a_new_code_that_replaces_the_old(): void
    {
        $user = $this->pendingUser();
        $first = $this->sentCode($user);

        $this->postJson(self::RESEND, ['mobile' => '9876543210'])->assertOk()->assertJsonPath('data.resend_in', 59);
        Notification::assertSentToTimes($user, VerificationCode::class, 1); // still cooling down

        $this->travel(60)->seconds();
        $this->postJson(self::RESEND, ['mobile' => '9876543210'])->assertOk();
        Notification::assertSentToTimes($user, VerificationCode::class, 2);

        $second = $this->sentCode($user);
        if ($first !== $second) {
            $this->postJson(self::VERIFY, ['mobile' => '9876543210', 'otp' => $first])->assertStatus(422);
        }
        $this->postJson(self::VERIFY, ['mobile' => '9876543210', 'otp' => $second])->assertOk();
    }

    public function test_resend_does_not_reveal_whether_a_number_is_registered(): void
    {
        Notification::fake();
        User::factory()->create(['mobile' => '9111111111']); // verified: nothing to resend

        $unknown = $this->postJson(self::RESEND, ['mobile' => '9222222222']);
        $verified = $this->postJson(self::RESEND, ['mobile' => '9111111111']);

        $this->assertSame($unknown->json(), $verified->json());
        Notification::assertNothingSent();
    }

    public function test_a_code_for_one_user_does_not_verify_another(): void
    {
        $alice = $this->pendingUser();
        $bob = User::factory()->unverifiedMobile()->create(['mobile' => '9333333333']);
        $this->postJson(self::RESEND, ['mobile' => '9333333333']);

        $this->postJson(self::VERIFY, ['mobile' => '9333333333', 'otp' => $this->sentCode($alice)])
            ->assertStatus(422);
        $this->assertNull($bob->fresh()->mobile_verified_at);
    }

    public function test_suspended_pending_accounts_cannot_be_verified(): void
    {
        Notification::fake();
        $user = User::factory()->unverifiedMobile()->suspended()->create(['mobile' => '9444444444']);
        $this->postJson(self::RESEND, ['mobile' => '9444444444']);

        Notification::assertNothingSent();
        $this->assertNull($user->fresh()->mobile_verified_at);
    }
}
