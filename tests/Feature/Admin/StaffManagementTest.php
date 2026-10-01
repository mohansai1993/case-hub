<?php

namespace Tests\Feature\Admin;

use App\Enums\AdminStatus;
use App\Models\Admin;
use App\Models\Role;
use App\Notifications\PasswordResetOtp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class StaffManagementTest extends TestCase
{
    use RefreshDatabase;

    private Admin $super;

    protected function setUp(): void
    {
        parent::setUp();

        $this->super = Admin::factory()->superAdmin()->create();
    }

    private function asSuper(): static
    {
        return $this->actingAs($this->super, 'admin');
    }

    private function asStaff(array $permissions = []): static
    {
        return $this->actingAs(Admin::factory()->withPermissions($permissions)->create(), 'admin');
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Rahul Sharma',
            'email' => 'rahul@casehub.com',
            'mobile' => '9876543210',
            'role_id' => Role::factory()->create()->id,
            'status' => 'active',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
        ], $overrides);
    }

    // ---- Access -------------------------------------------------------------

    public function test_only_super_admin_can_reach_staff_pages(): void
    {
        $member = Admin::factory()->create();

        $this->asStaff(['dashboard.view'])->get(route('admin.staff'))->assertForbidden();
        $this->asStaff(['dashboard.view'])->get(route('admin.create-staff'))->assertForbidden();
        $this->asStaff(['dashboard.view'])->get(route('admin.staff-details', $member))->assertForbidden();
        $this->asSuper()->get(route('admin.staff'))->assertOk();
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('admin.staff'))->assertRedirect(route('login'));
    }

    // ---- Super Admin accounts are never shown here ---------------------------

    public function test_super_admin_accounts_never_appear_in_the_staff_list(): void
    {
        $other = Admin::factory()->superAdmin()->create(['name' => 'Hidden Super']);

        $this->asSuper()->get(route('admin.staff'))->assertDontSee('Hidden Super');
    }

    public function test_a_super_admins_details_cannot_be_opened_through_staff_urls(): void
    {
        $other = Admin::factory()->superAdmin()->create();

        $this->asSuper()->get(route('admin.staff-details', $other))->assertNotFound();
        $this->asSuper()->get(route('admin.staff.edit', $other))->assertNotFound();
        $this->asSuper()->postJson(route('admin.staff.toggle', $other))->assertNotFound();
        $this->asSuper()->postJson(route('admin.staff.update-role', $other), ['role_id' => Role::factory()->create()->id])->assertNotFound();
        $this->asSuper()->postJson(route('admin.staff.reset-password', $other))->assertNotFound();
    }

    public function test_the_super_admin_role_cannot_be_assigned_through_staff_forms(): void
    {
        $superRole = Role::firstWhere('slug', Role::SUPER_ADMIN_SLUG);

        $this->asSuper()->get(route('admin.create-staff'))->assertDontSee('value="' . $superRole->id . '"', false);

        $this->asSuper()->postJson(route('admin.staff.store'), $this->validPayload(['role_id' => $superRole->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('role_id');
    }

    // ---- List -----------------------------------------------------------------

    public function test_staff_list_searches_and_filters(): void
    {
        $role = Role::factory()->create(['name' => 'Case Manager']);
        Admin::factory()->create(['name' => 'Rahul Sharma', 'role_id' => $role->id]);
        Admin::factory()->inactive()->create(['name' => 'Priya Verma']);

        $this->asSuper()->get(route('admin.staff', ['q' => 'rahul']))
            ->assertSee('Rahul Sharma')->assertDontSee('Priya Verma');

        $this->asSuper()->get(route('admin.staff', ['role' => $role->id]))
            ->assertSee('Rahul Sharma')->assertDontSee('Priya Verma');

        $this->asSuper()->get(route('admin.staff', ['status' => 'inactive']))
            ->assertSee('Priya Verma')->assertDontSee('Rahul Sharma');
    }

    // ---- Create -----------------------------------------------------------------

    public function test_a_staff_member_can_be_created(): void
    {
        $role = Role::factory()->create();

        $response = $this->asSuper()->postJson(route('admin.staff.store'), $this->validPayload(['role_id' => $role->id]))
            ->assertCreated();

        $admin = Admin::firstWhere('email', 'rahul@casehub.com');
        $this->assertNotNull($admin);
        $this->assertSame($role->id, $admin->role_id);
        $this->assertTrue($admin->isActive());
        $response->assertJsonPath('data.id', $admin->id);
    }

    public function test_email_and_mobile_must_be_unique(): void
    {
        Admin::factory()->create(['email' => 'taken@casehub.com', 'mobile' => '9999999999']);

        $this->asSuper()->postJson(route('admin.staff.store'), $this->validPayload(['email' => 'taken@casehub.com']))
            ->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->asSuper()->postJson(route('admin.staff.store'), $this->validPayload(['mobile' => '9999999999']))
            ->assertUnprocessable()->assertJsonValidationErrors('mobile');
    }

    public function test_mobile_must_look_like_an_indian_mobile_number(): void
    {
        $this->asSuper()->postJson(route('admin.staff.store'), $this->validPayload(['mobile' => '12345']))
            ->assertUnprocessable()->assertJsonValidationErrors('mobile');
    }

    public function test_weak_password_is_rejected(): void
    {
        $this->asSuper()->postJson(route('admin.staff.store'), $this->validPayload([
            'password' => 'weak', 'password_confirmation' => 'weak',
        ]))->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_a_disabled_role_cannot_be_newly_assigned(): void
    {
        $role = Role::factory()->create(['is_active' => false]);

        $this->asSuper()->postJson(route('admin.staff.store'), $this->validPayload(['role_id' => $role->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('role_id');
    }

    // ---- Update -----------------------------------------------------------------

    public function test_a_staff_member_can_be_updated_without_changing_the_password(): void
    {
        $member = Admin::factory()->create(['name' => 'Old Name']);
        $originalHash = $member->password;

        $this->asSuper()->putJson(route('admin.staff.update', $member), [
            'name' => 'New Name',
            'email' => $member->email,
            'mobile' => $member->mobile,
            'role_id' => $member->role_id,
            'status' => 'active',
        ])->assertOk();

        $member->refresh();
        $this->assertSame('New Name', $member->name);
        $this->assertSame($originalHash, $member->password);
    }

    public function test_an_unchanged_disabled_role_can_be_kept_on_update(): void
    {
        $role = Role::factory()->create(['is_active' => true]);
        $member = Admin::factory()->create(['role_id' => $role->id]);
        $role->update(['is_active' => false]);

        $this->asSuper()->putJson(route('admin.staff.update', $member), [
            'name' => $member->name,
            'email' => $member->email,
            'mobile' => $member->mobile,
            'role_id' => $role->id,
            'status' => 'active',
        ])->assertOk();
    }

    // ---- Toggle -----------------------------------------------------------------

    public function test_a_staff_member_can_be_deactivated_and_activated(): void
    {
        $member = Admin::factory()->create();

        $this->asSuper()->postJson(route('admin.staff.toggle', $member))
            ->assertOk()->assertJsonPath('data.status', 'inactive');
        $this->assertSame(AdminStatus::Inactive, $member->fresh()->status);

        $this->asSuper()->postJson(route('admin.staff.toggle', $member))
            ->assertOk()->assertJsonPath('data.status', 'active');
    }

    // ---- Role reassignment -----------------------------------------------------------------

    public function test_a_staff_members_role_can_be_changed(): void
    {
        $member = Admin::factory()->create();
        $newRole = Role::factory()->create();

        $this->asSuper()->postJson(route('admin.staff.update-role', $member), ['role_id' => $newRole->id])
            ->assertOk();

        $this->assertSame($newRole->id, $member->fresh()->role_id);
    }

    public function test_a_disabled_role_cannot_be_assigned_through_reassignment(): void
    {
        $member = Admin::factory()->create();
        $disabledRole = Role::factory()->create(['is_active' => false]);

        $this->asSuper()->postJson(route('admin.staff.update-role', $member), ['role_id' => $disabledRole->id])
            ->assertUnprocessable();

        $this->assertNotSame($disabledRole->id, $member->fresh()->role_id);
    }

    // ---- Password reset -----------------------------------------------------------------

    public function test_reset_password_sends_an_otp_to_the_staff_member(): void
    {
        Notification::fake();

        $member = Admin::factory()->create();

        $this->asSuper()->postJson(route('admin.staff.reset-password', $member))->assertOk();

        Notification::assertSentTo($member, PasswordResetOtp::class);
    }
}
