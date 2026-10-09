<?php

namespace Tests\Feature\Api;

use App\Enums\VerificationStatus;
use App\Models\User;
use App\Notifications\VerificationCode;
use Illuminate\Support\Facades\Notification;

class LoginTest extends ApiTestCase
{
    private const LOGIN = '/api/v1/auth/login';

    private function login(array $override = [], array $headers = [])
    {
        return $this->postJson(self::LOGIN, array_merge([
            'type' => 'client',
            'identifier' => 'rahul@example.com',
            'password' => self::PASSWORD,
        ], $override), $headers);
    }

    public function test_client_logs_in_with_email(): void
    {
        $user = User::factory()->create(['email' => 'rahul@example.com']);

        $this->login(['device_name' => 'iPhone 15'])
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->user_id)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonMissingPath('data.user.password');

        $this->assertSame('iPhone 15', $user->tokens()->first()->name);
        $this->assertSame(['client'], $user->tokens()->first()->abilities);
    }

    public function test_login_with_mobile_in_any_format(): void
    {
        User::factory()->create(['mobile' => '9876543210']);

        foreach (['9876543210', '+91 98765 43210', '098765-43210'] as $mobile) {
            $this->login(['identifier' => $mobile])->assertOk();
        }
    }

    public function test_lawyer_logs_in_on_the_lawyer_tab_and_sees_their_profile(): void
    {
        User::factory()->lawyer(VerificationStatus::Verified)->create(['email' => 'rahul@example.com']);

        $this->login(['type' => 'lawyer'])
            ->assertOk()
            ->assertJsonPath('data.user.type', 'lawyer')
            ->assertJsonPath('data.user.lawyer.verification_status', 'verified');
    }

    public function test_an_unverified_lawyer_can_still_log_in(): void
    {
        User::factory()->lawyer(VerificationStatus::Pending)->create(['email' => 'rahul@example.com']);

        $this->login(['type' => 'lawyer'])
            ->assertOk()
            ->assertJsonPath('data.user.lawyer.verification_status', 'pending');
    }

    public function test_a_rejected_lawyer_can_still_log_in(): void
    {
        User::factory()->lawyer(VerificationStatus::Rejected)->create(['email' => 'rahul@example.com']);

        $this->login(['type' => 'lawyer'])
            ->assertOk()
            ->assertJsonPath('data.user.lawyer.verification_status', 'rejected');
    }

    public function test_a_verified_lawyer_who_gets_suspended_is_blocked_by_suspension_not_verification(): void
    {
        User::factory()->lawyer(VerificationStatus::Verified)->suspended()->create(['email' => 'rahul@example.com']);

        $this->login(['type' => 'lawyer'])->assertStatus(403)->assertJsonPath('code', 'account_inactive');
    }

    public function test_the_wrong_tab_looks_exactly_like_a_wrong_password(): void
    {
        User::factory()->lawyer()->create(['email' => 'rahul@example.com']);

        $wrongTab = $this->login(['type' => 'client']);
        $wrongPassword = $this->login(['type' => 'lawyer', 'password' => 'Nope@12345']);
        $unknown = $this->login(['identifier' => 'nobody@example.com']);

        foreach ([$wrongTab, $wrongPassword, $unknown] as $response) {
            $response->assertStatus(401)->assertJsonPath('code', 'invalid_credentials');
        }
        $this->assertSame($wrongTab->json(), $unknown->json());
        $this->assertSame($wrongPassword->json(), $unknown->json());
    }

    public function test_login_validation(): void
    {
        $this->postJson(self::LOGIN, [])->assertStatus(422)->assertJsonValidationErrors(['type', 'identifier', 'password']);
        $this->login(['type' => 'admin'])->assertStatus(422)->assertJsonValidationErrors('type');
    }

    public function test_unverified_account_is_sent_to_the_otp_screen_after_a_correct_password(): void
    {
        Notification::fake();
        $user = User::factory()->unverifiedEmail()->create(['email' => 'rahul@example.com']);

        $this->login()
            ->assertStatus(403)
            ->assertJsonPath('code', 'email_not_verified')
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonMissingPath('data.token');

        Notification::assertSentTo($user, VerificationCode::class);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_unverified_status_is_not_revealed_for_a_wrong_password(): void
    {
        Notification::fake();
        User::factory()->unverifiedEmail()->create(['email' => 'rahul@example.com']);

        $this->login(['password' => 'Wrong@12345'])->assertStatus(401)->assertJsonPath('code', 'invalid_credentials');

        Notification::assertNothingSent();
    }

    public function test_suspended_and_inactive_accounts_cannot_log_in(): void
    {
        User::factory()->suspended()->create(['email' => 'rahul@example.com']);
        $this->login()->assertStatus(403)->assertJsonPath('code', 'account_inactive');

        User::query()->delete();
        User::factory()->inactive()->create(['email' => 'rahul@example.com']);
        $this->login()->assertStatus(403)->assertJsonPath('code', 'account_inactive');
    }

    public function test_suspending_an_account_blocks_tokens_already_issued(): void
    {
        $user = User::factory()->create();
        $headers = $this->bearer($user);

        $this->getJson('/api/v1/auth/me', $headers)->assertOk();

        $user->forceFill(['status' => 'suspended'])->save();
        $this->app['auth']->forgetGuards(); // the test app would otherwise reuse the resolved user

        $this->getJson('/api/v1/auth/me', $headers)->assertStatus(403)->assertJsonPath('code', 'account_inactive');
    }

    public function test_login_locks_out_after_five_failures_even_for_the_right_password(): void
    {
        User::factory()->create(['email' => 'rahul@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->login(['password' => 'Wrong@12345'])->assertStatus(401);
        }

        $this->login()->assertStatus(429)->assertHeader('Retry-After');
        $this->login(['identifier' => 'RAHUL@example.com'])->assertStatus(429);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_remember_me_issues_a_longer_lived_token(): void
    {
        User::factory()->create(['email' => 'rahul@example.com']);

        $short = $this->login()->json('data.expires_at');
        $long = $this->login(['remember' => true])->json('data.expires_at');

        $this->assertTrue(now()->addMinutes(config('casehub.api_tokens.ttl'))->diffInMinutes($short, true) < 1);
        $this->assertTrue(now()->addMinutes(config('casehub.api_tokens.remember_ttl'))->diffInMinutes($long, true) < 1);
    }

    public function test_expired_tokens_are_rejected(): void
    {
        User::factory()->create(['email' => 'rahul@example.com']);
        $token = $this->login()->json('data.token');

        $this->travel(config('casehub.api_tokens.ttl') + 1)->minutes();

        $this->getJson('/api/v1/auth/me', ['Authorization' => "Bearer $token"])->assertStatus(401);
    }

    public function test_only_the_ten_newest_devices_stay_signed_in(): void
    {
        $user = User::factory()->create(['email' => 'rahul@example.com']);

        for ($i = 1; $i <= 12; $i++) {
            $this->login(['device_name' => "device-$i"])->assertOk();
        }

        $this->assertSame(10, $user->tokens()->count());
        $this->assertFalse($user->tokens()->where('name', 'device-1')->exists());
        $this->assertTrue($user->tokens()->where('name', 'device-12')->exists());
    }

    public function test_logout_signs_out_only_this_device(): void
    {
        $user = User::factory()->create(['email' => 'rahul@example.com']);
        $a = $this->login(['device_name' => 'a'])->json('data.token');
        $b = $this->login(['device_name' => 'b'])->json('data.token');

        $this->postJson('/api/v1/auth/logout', [], ['Authorization' => "Bearer $a"])->assertOk();

        $this->assertSame(1, $user->tokens()->count());
        $this->assertSame('b', $user->tokens()->first()->name);

        $this->app['auth']->forgetGuards(); // the test app would otherwise reuse the resolved user
        $this->getJson('/api/v1/auth/me', ['Authorization' => "Bearer $b"])->assertOk();
    }

    public function test_logout_all_signs_out_every_device(): void
    {
        $user = User::factory()->create(['email' => 'rahul@example.com']);
        $a = $this->login(['device_name' => 'a'])->json('data.token');
        $this->login(['device_name' => 'b']);

        $this->postJson('/api/v1/auth/logout-all', [], ['Authorization' => "Bearer $a"])->assertOk();

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_protected_routes_need_a_valid_token(): void
    {
        $this->getJson('/api/v1/auth/me')->assertStatus(401);
        $this->getJson('/api/v1/auth/me', ['Authorization' => 'Bearer not-a-real-token'])->assertStatus(401);
        $this->postJson('/api/v1/auth/logout')->assertStatus(401);
    }

    public function test_an_admin_session_is_not_an_api_login(): void
    {
        $admin = \App\Models\Admin::factory()->superAdmin()->create();

        $this->actingAs($admin, 'admin')->getJson('/api/v1/auth/me')->assertStatus(401);
    }
}
