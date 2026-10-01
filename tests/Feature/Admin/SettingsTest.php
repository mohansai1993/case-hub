<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Services\Auth\AuthAuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private Admin $super;

    protected function setUp(): void
    {
        parent::setUp();

        $this->super = Admin::factory()->superAdmin()->create(['password' => 'OldPassword@123']);
    }

    private function asSuper(): static
    {
        return $this->actingAs($this->super, 'admin');
    }

    private function asStaff(array $permissions = []): Admin
    {
        $admin = Admin::factory()->withPermissions($permissions)->create(['password' => 'OldPassword@123']);
        $this->actingAs($admin, 'admin');

        return $admin;
    }

    // ---- Access -------------------------------------------------------------

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('admin.settings'))->assertRedirect(route('login'));
    }

    public function test_settings_needs_the_settings_permission(): void
    {
        $this->asStaff(['dashboard.view']);
        $this->get(route('admin.settings'))->assertForbidden();
    }

    // ---- Profile --------------------------------------------------------------

    public function test_settings_shows_the_signed_in_admins_own_profile(): void
    {
        $this->asSuper();

        $this->get(route('admin.settings'))
            ->assertSee($this->super->name)
            ->assertSee($this->super->email);
    }

    public function test_plan_management_is_only_shown_with_the_manage_permission(): void
    {
        $this->asStaff(['settings.view']);
        $this->get(route('admin.settings'))->assertDontSee('Manage Subscription Plans');

        $this->asStaff(['settings.view', 'subscriptions.manage']);
        $this->get(route('admin.settings'))->assertSee('Manage Subscription Plans');
    }

    // ---- Password change --------------------------------------------------------

    public function test_the_password_can_be_changed_with_the_correct_current_password(): void
    {
        $this->asSuper()->putJson(route('admin.settings.password'), [
            'current_password' => 'OldPassword@123',
            'password' => 'BrandNewPass9',
            'password_confirmation' => 'BrandNewPass9',
        ])->assertOk();

        $this->assertTrue(Hash::check('BrandNewPass9', $this->super->fresh()->password));
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $this->asSuper()->putJson(route('admin.settings.password'), [
            'current_password' => 'WrongPassword@1',
            'password' => 'BrandNewPass9',
            'password_confirmation' => 'BrandNewPass9',
        ])->assertUnprocessable()->assertJsonValidationErrors('current_password');

        $this->assertTrue(Hash::check('OldPassword@123', $this->super->fresh()->password));
    }

    public function test_weak_new_password_is_rejected(): void
    {
        $this->asSuper()->putJson(route('admin.settings.password'), [
            'current_password' => 'OldPassword@123',
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_changing_the_password_is_audited(): void
    {
        $this->asSuper()->putJson(route('admin.settings.password'), [
            'current_password' => 'OldPassword@123',
            'password' => 'BrandNewPass9',
            'password_confirmation' => 'BrandNewPass9',
        ])->assertOk();

        $this->assertDatabaseHas('admin_auth_logs', [
            'admin_id' => $this->super->id,
            'event' => AuthAuditLogger::PASSWORD_CHANGED,
        ]);
    }

    // ---- Delete account --------------------------------------------------------

    public function test_a_super_admin_cannot_delete_their_own_account(): void
    {
        $this->asSuper()->deleteJson(route('admin.settings.destroy'), ['confirmation' => 'DELETE'])
            ->assertUnprocessable();

        $this->assertModelExists($this->super);
    }

    public function test_a_staff_member_can_delete_their_own_account_with_confirmation(): void
    {
        $admin = $this->asStaff(['settings.view']);

        $this->deleteJson(route('admin.settings.destroy'), ['confirmation' => 'nope'])->assertUnprocessable();
        $this->assertModelExists($admin);

        $this->deleteJson(route('admin.settings.destroy'), ['confirmation' => 'DELETE'])->assertOk();
        $this->assertModelMissing($admin);
    }

    public function test_the_delete_account_section_is_hidden_for_the_super_admin(): void
    {
        $this->asSuper();
        $this->get(route('admin.settings'))->assertDontSee('Delete Admin Account');
    }

    public function test_the_delete_account_section_is_shown_for_staff(): void
    {
        $this->asStaff(['settings.view']);
        $this->get(route('admin.settings'))->assertSee('Delete Admin Account');
    }

    // ---- Logout -----------------------------------------------------------------

    public function test_logout_button_posts_to_the_real_logout_route(): void
    {
        $this->asSuper()->get(route('admin.settings'))
            ->assertSee('action="' . route('logout') . '"', false);
    }
}
