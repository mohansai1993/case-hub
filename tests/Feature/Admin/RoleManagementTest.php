<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleManagementTest extends TestCase
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

    // ---- Access -------------------------------------------------------------

    public function test_only_super_admin_can_reach_roles_pages(): void
    {
        $role = Role::factory()->create();

        $this->asStaff(['dashboard.view'])->get(route('admin.roles'))->assertForbidden();
        $this->asStaff(['dashboard.view'])->get(route('admin.create-role'))->assertForbidden();
        $this->asStaff(['dashboard.view'])->get(route('admin.role-details', $role))->assertForbidden();
        $this->asSuper()->get(route('admin.roles'))->assertOk();
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('admin.roles'))->assertRedirect(route('login'));
        $this->postJson(route('admin.roles.store'), [])->assertUnauthorized();
    }

    // ---- List -----------------------------------------------------------------

    public function test_roles_list_shows_user_counts(): void
    {
        $role = Role::factory()->create(['name' => 'Support Manager']);
        Admin::factory()->count(2)->create(['role_id' => $role->id]);

        $this->asSuper()->get(route('admin.roles'))
            ->assertSee('Support Manager')
            ->assertSee('2 Users');
    }

    // ---- Create -----------------------------------------------------------------

    public function test_a_role_can_be_created_with_permissions(): void
    {
        $response = $this->asSuper()->postJson(route('admin.roles.store'), [
            'name' => 'Billing Administrator',
            'description' => 'Manage subscriptions',
            'permissions' => ['subscriptions.view', 'subscriptions.manage'],
        ])->assertCreated();

        $role = Role::firstWhere('name', 'Billing Administrator');
        $this->assertNotNull($role);
        $this->assertEqualsCanonicalizing(['subscriptions.view', 'subscriptions.manage'], $role->permissionKeys());
        $this->assertSame('Custom Role', $role->tag);
        $response->assertJsonPath('data.id', $role->id);
    }

    public function test_role_name_must_be_unique(): void
    {
        Role::factory()->create(['name' => 'Case Manager']);

        $this->asSuper()->postJson(route('admin.roles.store'), [
            'name' => 'Case Manager',
            'permissions' => ['dashboard.view'],
        ])->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_at_least_one_permission_is_required(): void
    {
        $this->asSuper()->postJson(route('admin.roles.store'), [
            'name' => 'Empty Role',
            'permissions' => [],
        ])->assertUnprocessable()->assertJsonValidationErrors('permissions');
    }

    public function test_unknown_permission_keys_are_rejected(): void
    {
        $this->asSuper()->postJson(route('admin.roles.store'), [
            'name' => 'Sneaky Role',
            'permissions' => ['roles.delete-everything'],
        ])->assertUnprocessable()->assertJsonValidationErrors('permissions.0');
    }

    // ---- Update -----------------------------------------------------------------

    public function test_a_role_can_be_updated(): void
    {
        $role = Role::factory()->withPermissions(['dashboard.view'])->create(['name' => 'Old Name']);

        $this->asSuper()->putJson(route('admin.roles.update', $role), [
            'name' => 'New Name',
            'description' => 'Updated',
            'permissions' => ['clients.view'],
        ])->assertOk();

        $fresh = Role::find($role->id);
        $this->assertSame('New Name', $fresh->name);
        $this->assertSame(['clients.view'], $fresh->permissionKeys());
    }

    public function test_the_super_admin_role_cannot_be_edited(): void
    {
        $superRole = Role::firstWhere('slug', Role::SUPER_ADMIN_SLUG);

        $this->asSuper()->get(route('admin.roles.edit', $superRole))->assertNotFound();
        $this->asSuper()->putJson(route('admin.roles.update', $superRole), [
            'name' => 'Hacked',
            'permissions' => ['dashboard.view'],
        ])->assertNotFound();
    }

    public function test_the_super_admin_role_never_appears_in_the_list_or_its_own_details_page(): void
    {
        $superRole = Role::firstWhere('slug', Role::SUPER_ADMIN_SLUG);
        Admin::factory()->create(['role_id' => $superRole->id, 'name' => 'Hidden Super']);

        $this->asSuper()->get(route('admin.roles'))
            ->assertDontSee('Hidden Super')
            ->assertDontSee(route('admin.role-details', $superRole), false);

        $this->asSuper()->get(route('admin.role-details', $superRole))->assertNotFound();
    }

    // ---- Toggle -----------------------------------------------------------------

    public function test_a_custom_role_can_be_disabled_and_enabled(): void
    {
        $role = Role::factory()->create(['is_active' => true]);

        $this->asSuper()->postJson(route('admin.roles.toggle', $role))
            ->assertOk()->assertJsonPath('data.is_active', false);
        $this->assertFalse($role->fresh()->is_active);

        $this->asSuper()->postJson(route('admin.roles.toggle', $role))
            ->assertOk()->assertJsonPath('data.is_active', true);
    }

    public function test_the_super_admin_role_cannot_be_disabled(): void
    {
        $superRole = Role::firstWhere('slug', Role::SUPER_ADMIN_SLUG);

        $this->asSuper()->postJson(route('admin.roles.toggle', $superRole))->assertNotFound();
        $this->assertTrue($superRole->fresh()->is_active);
    }

    public function test_managing_roles_requires_being_super_admin_not_just_a_permission(): void
    {
        $role = Role::factory()->create();

        $this->asStaff(['dashboard.view'])->postJson(route('admin.roles.toggle', $role))->assertForbidden();
    }
}
