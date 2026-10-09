<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Notifications\VerificationCode;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

class PasswordResetTest extends ApiTestCase
{
    private const SEND = '/api/v1/auth/forgot-password';
    private const VERIFY = '/api/v1/auth/forgot-password/verify';
    private const RESET = '/api/v1/auth/reset-password';
    private const NEW_PASSWORD = 'Brand-New-Pass9';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->user = User::factory()->create(['email' => 'rahul@example.com', 'mobile' => '9876543210']);
    }

    private function token(): string
    {
        $this->postJson(self::SEND, ['identifier' => 'rahul@example.com'])->assertOk();

        return $this->postJson(self::VERIFY, ['identifier' => 'rahul@example.com', 'otp' => $this->sentCode($this->user)])
            ->assertOk()->json('data.reset_token');
    }

    private function reset(string $token, string $password = self::NEW_PASSWORD, string $identifier = 'rahul@example.com')
    {
        return $this->postJson(self::RESET, [
            'identifier' => $identifier,
            'reset_token' => $token,
            'password' => $password,
            'password_confirmation' => $password,
        ]);
    }

    public function test_full_flow_changes_the_password_and_signs_out_every_device(): void
    {
        $this->user->createToken('phone');
        $this->user->createToken('tablet');

        $this->reset($this->token())->assertOk();

        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $this->user->fresh()->password));
        $this->assertSame(0, $this->user->tokens()->count());
        $this->assertDatabaseCount('user_otps', 0);

        $this->postJson('/api/v1/auth/login', ['type' => 'client', 'identifier' => 'rahul@example.com', 'password' => self::NEW_PASSWORD])->assertOk();
    }

    public function test_otp_is_emailed_for_an_email_identifier(): void
    {
        $this->postJson(self::SEND, ['identifier' => 'rahul@example.com'])->assertOk();

        Notification::assertSentTo($this->user, VerificationCode::class, fn ($n, $channels) => $channels === ['mail']);
    }

    public function test_otp_is_emailed_for_a_mobile_identifier_too(): void
    {
        $this->postJson(self::SEND, ['identifier' => '+91 98765 43210'])->assertOk();

        Notification::assertSentTo($this->user, VerificationCode::class, fn ($n, $channels) => $channels === ['mail']);
    }

    public function test_the_answer_is_identical_for_unknown_and_unverified_accounts(): void
    {
        User::factory()->unverifiedEmail()->create(['email' => 'pending@example.com', 'mobile' => '9555555555']);

        $known = $this->postJson(self::SEND, ['identifier' => 'rahul@example.com'])->json();
        $unknown = $this->postJson(self::SEND, ['identifier' => 'nobody@example.com'])->json();
        $pending = $this->postJson(self::SEND, ['identifier' => 'pending@example.com'])->json();

        $this->assertSame($known, $unknown);
        $this->assertSame($known, $pending);
        Notification::assertCount(1);
    }

    public function test_suspended_accounts_cannot_reset_their_way_back_in(): void
    {
        $this->user->forceFill(['status' => 'suspended'])->save();

        $this->postJson(self::SEND, ['identifier' => 'rahul@example.com'])->assertOk();

        Notification::assertNothingSent();
    }

    public function test_wrong_otp_and_attempt_limit(): void
    {
        $this->postJson(self::SEND, ['identifier' => 'rahul@example.com']);
        $code = $this->sentCode($this->user);

        for ($i = 0; $i < config('otp.app.max_attempts'); $i++) {
            $this->postJson(self::VERIFY, ['identifier' => 'rahul@example.com', 'otp' => $this->wrongCode($code)])
                ->assertStatus(422)->assertJsonPath('code', 'invalid_otp');
        }

        $this->postJson(self::VERIFY, ['identifier' => 'rahul@example.com', 'otp' => $code])->assertStatus(422);
    }

    public function test_reset_requires_a_verified_token(): void
    {
        $this->postJson(self::SEND, ['identifier' => 'rahul@example.com']);

        $this->reset(str_repeat('a', 64))->assertStatus(422)->assertJsonPath('code', 'invalid_reset_token');
    }

    public function test_reset_token_is_single_use(): void
    {
        $token = $this->token();

        $this->reset($token)->assertOk();
        $this->reset($token, 'Another-Pass-77')->assertStatus(422);
    }

    public function test_reset_token_expires(): void
    {
        $token = $this->token();

        $this->travel(config('otp.app.reset_token_ttl') + 1)->minutes();

        $this->reset($token)->assertStatus(422);
    }

    public function test_reset_token_only_works_for_its_own_account(): void
    {
        $other = User::factory()->create(['email' => 'other@example.com']);
        $token = $this->token();

        $this->reset($token, self::NEW_PASSWORD, 'other@example.com')->assertStatus(422);

        $this->assertTrue(Hash::check(self::PASSWORD, $other->fresh()->password));
    }

    public function test_weak_passwords_are_rejected_and_the_token_survives_the_typo(): void
    {
        $token = $this->token();

        $this->reset($token, 'short')->assertStatus(422)->assertJsonValidationErrors('password');
        $this->reset($token, 'alllowercase1')->assertStatus(422)->assertJsonValidationErrors('password');

        $this->reset($token)->assertOk();
    }

    public function test_reset_rotates_the_remember_token(): void
    {
        $this->user->forceFill(['remember_token' => 'old'])->save();

        $this->reset($this->token())->assertOk();

        $this->assertNotSame('old', $this->user->fresh()->remember_token);
    }
}
