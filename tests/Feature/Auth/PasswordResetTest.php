<?php

namespace Tests\Feature\Auth;

use App\Models\Admin;
use App\Models\AdminPasswordReset;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\PasswordResetOtp;
use Database\Factories\AdminFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const NEW_PASSWORD = 'Brand-New-Pass9';

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::factory()->superAdmin()->create(['mobile' => '9876543210']);
    }

    private function sendOtp(string $identifier = null)
    {
        return $this->postJson(route('password.otp.send'), ['identifier' => $identifier ?? $this->admin->email]);
    }

    /** The OTP the (faked) notification would have delivered. */
    private function sentCode(): string
    {
        $notification = Notification::sent($this->admin, PasswordResetOtp::class)->last();

        preg_match('/\b(\d{6})\b/', $notification->toSms($this->admin), $m);

        return $m[1];
    }

    private function verify(string $otp, string $identifier = null)
    {
        return $this->postJson(route('password.otp.verify'), [
            'identifier' => $identifier ?? $this->admin->email,
            'otp' => $otp,
        ]);
    }

    private function reset(string $token, string $password = self::NEW_PASSWORD, string $identifier = null)
    {
        return $this->postJson(route('password.reset'), [
            'identifier' => $identifier ?? $this->admin->email,
            'reset_token' => $token,
            'password' => $password,
            'password_confirmation' => $password,
        ]);
    }

    public function test_otp_is_emailed_for_an_email_identifier(): void
    {
        Notification::fake();

        $this->sendOtp()->assertOk()->assertJsonPath('resend_in', 60);

        Notification::assertSentTo($this->admin, PasswordResetOtp::class, fn ($n, $channels) => $channels === ['mail']);
    }

    public function test_otp_is_texted_for_a_mobile_identifier(): void
    {
        Notification::fake();

        $this->sendOtp('+91 98765 43210')->assertOk();

        Notification::assertSentTo($this->admin, PasswordResetOtp::class, fn ($n, $channels) => $channels === [SmsChannel::class]);
    }

    public function test_response_is_identical_whether_or_not_the_account_exists(): void
    {
        Notification::fake();

        $known = $this->sendOtp()->json();
        $unknown = $this->sendOtp('nobody@casehub.test')->json();
        $unknownMobile = $this->sendOtp('9123456780')->json();

        $this->assertSame($known, $unknown);
        $this->assertSame($known, $unknownMobile);
        Notification::assertSentToTimes($this->admin, PasswordResetOtp::class, 1);
        Notification::assertCount(1);
    }

    public function test_inactive_admins_get_no_otp_and_no_hint(): void
    {
        Notification::fake();
        $inactive = Admin::factory()->inactive()->create();

        $this->sendOtp($inactive->email)->assertOk();

        Notification::assertNothingSent();
    }

    public function test_invalid_identifier_is_a_validation_error(): void
    {
        $this->sendOtp('not valid')->assertStatus(422)->assertJsonValidationErrors('identifier');
    }

    public function test_resend_is_blocked_during_the_cooldown_then_allowed(): void
    {
        Notification::fake();

        $this->sendOtp();
        $this->sendOtp();
        Notification::assertSentToTimes($this->admin, PasswordResetOtp::class, 1);

        $this->travel(61)->seconds();
        $this->sendOtp();
        Notification::assertSentToTimes($this->admin, PasswordResetOtp::class, 2);
    }

    public function test_only_hashes_are_stored(): void
    {
        Notification::fake();
        $this->sendOtp();
        $code = $this->sentCode();

        $row = AdminPasswordReset::firstOrFail();

        $this->assertNotSame($code, $row->otp_hash);
        $this->assertSame(64, strlen($row->otp_hash));
        $this->assertSame(1, AdminPasswordReset::count());
    }

    public function test_full_flow_changes_the_password(): void
    {
        Notification::fake();
        $this->sendOtp();

        $token = $this->verify($this->sentCode())->assertOk()->json('reset_token');
        $this->assertSame(64, strlen($token));

        $this->reset($token)->assertOk();

        $this->post('/login', ['identifier' => $this->admin->email, 'password' => AdminFactory::PASSWORD])
            ->assertSessionHasErrors('identifier');
        $this->post('/login', ['identifier' => $this->admin->email, 'password' => self::NEW_PASSWORD])
            ->assertRedirect(route('admin.home'));

        $this->assertDatabaseCount('admin_password_resets', 0);
        $this->assertDatabaseHas('admin_auth_logs', ['event' => 'password.reset', 'admin_id' => $this->admin->id]);
    }

    public function test_wrong_otp_is_rejected_without_saying_why(): void
    {
        Notification::fake();
        $this->sendOtp();
        $wrong = $this->sentCode() === '000000' ? '111111' : '000000';

        $this->verify($wrong)->assertStatus(422)->assertJsonPath('message', 'Invalid or expired OTP. Please try again or request a new one.');
    }

    public function test_otp_is_burned_after_too_many_wrong_guesses(): void
    {
        Notification::fake();
        $this->sendOtp();
        $code = $this->sentCode();
        $wrong = $code === '000000' ? '111111' : '000000';

        for ($i = 0; $i < config('otp.max_attempts'); $i++) {
            $this->verify($wrong)->assertStatus(422);
        }

        $this->verify($code)->assertStatus(422);
    }

    public function test_expired_otp_is_rejected(): void
    {
        Notification::fake();
        $this->sendOtp();
        $code = $this->sentCode();

        $this->travel(config('otp.ttl') + 1)->minutes();

        $this->verify($code)->assertStatus(422);
    }

    public function test_otp_is_single_use(): void
    {
        Notification::fake();
        $this->sendOtp();
        $code = $this->sentCode();

        $this->verify($code)->assertOk();
        $this->verify($code)->assertStatus(422);
    }

    public function test_a_new_otp_replaces_the_old_one(): void
    {
        Notification::fake();
        $this->sendOtp();
        $first = $this->sentCode();

        $this->travel(61)->seconds();
        $this->sendOtp();
        $second = $this->sentCode();

        if ($first !== $second) {
            $this->verify($first)->assertStatus(422);
        }
        $this->verify($second)->assertOk();
    }

    public function test_otp_for_one_admin_does_not_work_for_another(): void
    {
        Notification::fake();
        $other = Admin::factory()->create();
        $this->sendOtp();
        $this->postJson(route('password.otp.send'), ['identifier' => $other->email]);

        $this->verify($this->sentCode(), $other->email)->assertStatus(422);
    }

    public function test_reset_requires_a_verified_token(): void
    {
        Notification::fake();
        $this->sendOtp();

        $this->reset(str_repeat('a', 64))->assertStatus(422);
        $this->reset($this->sentCode() . str_repeat('a', 58))->assertStatus(422);
    }

    public function test_reset_token_is_single_use(): void
    {
        Notification::fake();
        $this->sendOtp();
        $token = $this->verify($this->sentCode())->json('reset_token');

        $this->reset($token)->assertOk();
        $this->reset($token, 'Another-Pass-77')->assertStatus(422);
    }

    public function test_reset_token_expires(): void
    {
        Notification::fake();
        $this->sendOtp();
        $token = $this->verify($this->sentCode())->json('reset_token');

        $this->travel(config('otp.reset_token_ttl') + 1)->minutes();

        $this->reset($token)->assertStatus(422);
    }

    public function test_reset_token_is_bound_to_the_account(): void
    {
        Notification::fake();
        $other = Admin::factory()->create();
        $this->sendOtp();
        $token = $this->verify($this->sentCode())->json('reset_token');

        $this->reset($token, self::NEW_PASSWORD, $other->email)->assertStatus(422);
        $this->assertTrue(\Hash::check(AdminFactory::PASSWORD, $other->fresh()->password));
    }

    public function test_weak_passwords_are_rejected_and_the_token_survives_the_typo(): void
    {
        Notification::fake();
        $this->sendOtp();
        $token = $this->verify($this->sentCode())->json('reset_token');

        $this->reset($token, 'short')->assertStatus(422)->assertJsonValidationErrors('password');
        $this->reset($token, 'alllowercase1')->assertStatus(422)->assertJsonValidationErrors('password');
        $this->reset($token, 'NoNumbersHere')->assertStatus(422)->assertJsonValidationErrors('password');

        $this->reset($token)->assertOk();
    }

    public function test_mismatched_confirmation_is_rejected(): void
    {
        $this->postJson(route('password.reset'), [
            'identifier' => $this->admin->email,
            'reset_token' => str_repeat('a', 64),
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => 'different',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_reset_rotates_the_remember_token(): void
    {
        Notification::fake();
        $this->admin->forceFill(['remember_token' => 'old-remember-token'])->save();
        $this->sendOtp();
        $token = $this->verify($this->sentCode())->json('reset_token');

        $this->reset($token)->assertOk();

        $this->assertNotSame('old-remember-token', $this->admin->fresh()->remember_token);
    }

    public function test_endpoints_are_unavailable_to_signed_in_admins(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->postJson(route('password.otp.send'), ['identifier' => $this->admin->email])
            ->assertStatus(302);
    }
}
