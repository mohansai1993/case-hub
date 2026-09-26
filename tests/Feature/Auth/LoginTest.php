<?php

namespace Tests\Feature\Auth;

use App\Models\Admin;
use App\Models\AdminAuthLog;
use Database\Factories\AdminFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    private const WRONG = 'Not-the-password-1';

    public function test_login_page_is_the_landing_page_for_guests(): void
    {
        $this->get('/')->assertOk()->assertSee('Welcome Back');
        $this->get('/login')->assertRedirect('/');
    }

    public function test_super_admin_can_login_with_email(): void
    {
        $admin = Admin::factory()->superAdmin()->create();

        $this->post('/login', ['identifier' => strtoupper($admin->email), 'password' => AdminFactory::PASSWORD])
            ->assertRedirect(route('admin.home'));

        $this->assertAuthenticatedAs($admin, 'admin');
        $this->followingRedirects()->get(route('admin.home'))->assertOk();
    }

    public function test_admin_can_login_with_mobile_in_any_common_format(): void
    {
        $admin = Admin::factory()->superAdmin()->create(['mobile' => '9876543210']);

        $this->post('/login', ['identifier' => '+91 98765-43210', 'password' => AdminFactory::PASSWORD])
            ->assertRedirect(route('admin.home'));

        $this->assertAuthenticatedAs($admin, 'admin');
    }

    public function test_wrong_password_and_unknown_account_give_the_same_error(): void
    {
        $admin = Admin::factory()->create();

        $this->from('/')->post('/login', ['identifier' => $admin->email, 'password' => self::WRONG])
            ->assertRedirect('/')->assertSessionHasErrors('identifier');
        $wrongPassword = session('errors')->first('identifier');

        $this->from('/')->post('/login', ['identifier' => 'nobody@casehub.test', 'password' => self::WRONG])
            ->assertRedirect('/')->assertSessionHasErrors('identifier');
        $unknownAccount = session('errors')->first('identifier');

        $this->assertSame($wrongPassword, $unknownAccount);
        $this->assertGuest('admin');
    }

    public function test_garbage_identifier_is_rejected_like_any_other_bad_credential(): void
    {
        $this->post('/login', ['identifier' => "x' OR '1'='1", 'password' => self::WRONG])
            ->assertSessionHasErrors('identifier');

        $this->assertGuest('admin');
    }

    public function test_required_fields_are_validated(): void
    {
        $this->post('/login', [])->assertSessionHasErrors(['identifier', 'password']);
    }

    public function test_inactive_admin_cannot_login_even_with_the_right_password(): void
    {
        $admin = Admin::factory()->inactive()->create();

        $this->post('/login', ['identifier' => $admin->email, 'password' => AdminFactory::PASSWORD])
            ->assertSessionHasErrors('identifier');

        $this->assertGuest('admin');
        $this->assertStringContainsString('deactivated', session('errors')->first('identifier'));
    }

    public function test_inactive_admin_with_a_wrong_password_learns_nothing(): void
    {
        $admin = Admin::factory()->inactive()->create();

        $this->post('/login', ['identifier' => $admin->email, 'password' => self::WRONG])
            ->assertSessionHasErrors('identifier');

        $this->assertStringNotContainsString('deactivated', session('errors')->first('identifier'));
    }

    public function test_login_is_locked_after_five_failures_even_for_the_right_password(): void
    {
        $admin = Admin::factory()->superAdmin()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['identifier' => $admin->email, 'password' => self::WRONG]);
        }

        $this->post('/login', ['identifier' => $admin->email, 'password' => AdminFactory::PASSWORD])
            ->assertSessionHasErrors('identifier');

        $this->assertStringContainsString('Too many login attempts', session('errors')->first('identifier'));
        $this->assertGuest('admin');
        $this->assertDatabaseHas('admin_auth_logs', ['event' => 'login.lockout']);
    }

    public function test_lockout_cannot_be_dodged_by_changing_identifier_case(): void
    {
        $admin = Admin::factory()->superAdmin()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['identifier' => $admin->email, 'password' => self::WRONG]);
        }

        $this->post('/login', ['identifier' => strtoupper($admin->email), 'password' => AdminFactory::PASSWORD]);

        $this->assertGuest('admin');
    }

    public function test_successful_login_resets_the_failure_counter(): void
    {
        $admin = Admin::factory()->superAdmin()->create();

        for ($i = 0; $i < 4; $i++) {
            $this->post('/login', ['identifier' => $admin->email, 'password' => self::WRONG]);
        }
        $this->post('/login', ['identifier' => $admin->email, 'password' => AdminFactory::PASSWORD]);
        $this->post('/logout');

        for ($i = 0; $i < 4; $i++) {
            $this->post('/login', ['identifier' => $admin->email, 'password' => self::WRONG]);
        }
        $this->post('/login', ['identifier' => $admin->email, 'password' => AdminFactory::PASSWORD]);

        $this->assertAuthenticatedAs($admin, 'admin');
    }

    public function test_remember_me_sets_the_remember_cookie_only_when_asked(): void
    {
        $admin = Admin::factory()->superAdmin()->create();

        $without = $this->post('/login', ['identifier' => $admin->email, 'password' => AdminFactory::PASSWORD]);
        $this->assertEmpty(collect($without->headers->getCookies())->filter(fn ($c) => str_starts_with($c->getName(), 'remember_admin_')));

        $this->post('/logout');

        $with = $this->post('/login', ['identifier' => $admin->email, 'password' => AdminFactory::PASSWORD, 'remember' => '1']);
        $this->assertNotEmpty(collect($with->headers->getCookies())->filter(fn ($c) => str_starts_with($c->getName(), 'remember_admin_')));
    }

    public function test_login_records_last_login_and_audit_trail(): void
    {
        $admin = Admin::factory()->superAdmin()->create();

        $this->post('/login', ['identifier' => $admin->email, 'password' => self::WRONG]);
        $this->post('/login', ['identifier' => $admin->email, 'password' => AdminFactory::PASSWORD]);

        $this->assertNotNull($admin->fresh()->last_login_at);
        $this->assertNotNull($admin->fresh()->last_login_ip);
        $this->assertSame(['login.failed', 'login.success'], AdminAuthLog::orderBy('id')->pluck('event')->all());
        $this->assertSame($admin->id, AdminAuthLog::where('event', 'login.success')->value('admin_id'));
    }

    public function test_no_password_or_otp_is_ever_written_to_the_audit_log(): void
    {
        $admin = Admin::factory()->create();

        $this->post('/login', ['identifier' => $admin->email, 'password' => self::WRONG]);

        $this->assertStringNotContainsString(self::WRONG, json_encode(AdminAuthLog::all()->toArray()));
    }

    public function test_signed_in_admin_is_sent_away_from_the_login_page(): void
    {
        $this->actingAs(Admin::factory()->superAdmin()->create(), 'admin')
            ->get('/')
            ->assertRedirect(route('admin.home'));
    }

    public function test_admin_lands_on_the_first_section_their_role_allows(): void
    {
        $admin = Admin::factory()->withPermissions(['lawyers.view'])->create();

        $this->post('/login', ['identifier' => $admin->email, 'password' => AdminFactory::PASSWORD]);

        $this->get(route('admin.home'))->assertRedirect(route('admin.lawyers'));
    }

    public function test_admin_whose_role_grants_nothing_sees_the_no_access_page(): void
    {
        $admin = Admin::factory()->create();

        $this->post('/login', ['identifier' => $admin->email, 'password' => AdminFactory::PASSWORD]);

        $this->get(route('admin.home'))->assertRedirect(route('admin.no-access'));
        $this->get(route('admin.no-access'))->assertOk()->assertSee('No sections assigned');
    }

    public function test_logout_ends_the_session(): void
    {
        $admin = Admin::factory()->superAdmin()->create();

        $this->actingAs($admin, 'admin')->post('/logout')->assertRedirect(route('login'));

        $this->assertGuest('admin');
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->assertDatabaseHas('admin_auth_logs', ['event' => 'logout', 'admin_id' => $admin->id]);
    }

    public function test_logout_requires_post_and_authentication(): void
    {
        $this->get('/logout')->assertStatus(405);
        $this->post('/logout')->assertRedirect(route('login'));
    }
}
