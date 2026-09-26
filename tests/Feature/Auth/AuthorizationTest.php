<?php

namespace Tests\Feature\Auth;

use App\Models\Admin;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_login_for_every_panel_route(): void
    {
        foreach (['dashboard', 'clients', 'lawyers', 'subscriptions', 'notifications', 'settings', 'roles', 'staff', 'no-access'] as $page) {
            $this->get(route("admin.$page"))->assertRedirect(route('login'));
        }
    }

    public function test_super_admin_can_open_every_section_including_roles_and_staff(): void
    {
        $this->actingAs(Admin::factory()->superAdmin()->create(), 'admin');

        foreach (['dashboard', 'clients', 'lawyers', 'subscriptions', 'notifications', 'settings', 'create-plan', 'roles', 'staff'] as $page) {
            $this->get(route("admin.$page"))->assertOk();
        }
    }

    public function test_a_role_only_opens_what_it_was_granted(): void
    {
        $this->actingAs(Admin::factory()->withPermissions(['clients.view'])->create(), 'admin');

        $this->get(route('admin.clients'))->assertOk();
        $this->get(route('admin.client-details', \App\Models\User::factory()->create()->user_id))->assertOk();
        $this->get(route('admin.lawyers'))->assertForbidden();
        $this->get(route('admin.dashboard'))->assertForbidden();
        $this->get(route('admin.settings'))->assertForbidden();
    }

    public function test_viewing_a_section_does_not_grant_managing_it(): void
    {
        $this->actingAs(Admin::factory()->withPermissions(['subscriptions.view'])->create(), 'admin');

        $this->get(route('admin.subscriptions'))->assertOk();
        $this->get(route('admin.create-plan'))->assertForbidden();
    }

    public function test_roles_and_staff_can_never_be_delegated(): void
    {
        $everything = array_keys(\App\Support\Permissions::all());
        $this->actingAs(Admin::factory()->withPermissions($everything)->create(), 'admin');

        foreach (['roles', 'create-role', 'role-details', 'staff', 'create-staff', 'staff-details'] as $page) {
            $this->get(route("admin.$page"))->assertForbidden();
        }
    }

    public function test_sidebar_only_lists_permitted_sections(): void
    {
        $this->actingAs(Admin::factory()->withPermissions(['clients.view', 'clients.update'])->create(), 'admin');

        $this->get(route('admin.clients'))
            ->assertSee(route('admin.clients'), false)
            ->assertDontSee(route('admin.lawyers'), false)
            ->assertDontSee(route('admin.roles'), false)
            ->assertDontSee(route('admin.staff'), false);
    }

    public function test_deactivating_an_admin_takes_effect_on_their_next_request(): void
    {
        $admin = Admin::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin')->get(route('admin.dashboard'))->assertOk();

        $admin->update(['status' => 'inactive']);

        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest('admin');
    }

    public function test_switching_a_role_off_removes_access_immediately(): void
    {
        $admin = Admin::factory()->withPermissions(['clients.view'])->create();
        $this->actingAs($admin, 'admin')->get(route('admin.clients'))->assertOk();

        $admin->role->update(['is_active' => false]);

        $this->get(route('admin.clients'))->assertForbidden();
    }

    public function test_panel_pages_are_not_cacheable(): void
    {
        $response = $this->actingAs(Admin::factory()->superAdmin()->create(), 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk();

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_permission_logic(): void
    {
        $super = Admin::factory()->superAdmin()->create();
        $staff = Admin::factory()->withPermissions(['clients.view'])->create();
        $inactive = Admin::factory()->superAdmin()->inactive()->create();

        $this->assertTrue($super->hasPermission('anything.at.all'));
        $this->assertTrue($staff->hasPermission('clients.view'));
        $this->assertFalse($staff->hasPermission('clients.delete'));
        $this->assertFalse($inactive->hasPermission('clients.view'), 'inactive admins hold nothing, not even Super Admin');
        $this->assertSame('admin.clients', $staff->homeRoute());
    }

    public function test_unknown_permission_keys_are_never_stored(): void
    {
        $role = Role::factory()->create();

        $role->syncPermissions(['clients.view', 'made.up', 123, 'clients.view']);

        $this->assertSame(['clients.view'], $role->permissionKeys());
    }

    public function test_system_role_is_protected(): void
    {
        $role = Role::factory()->superAdmin()->create();

        $this->expectException(LogicException::class);
        $role->delete();
    }

    public function test_system_role_cannot_be_deactivated(): void
    {
        $role = Role::factory()->superAdmin()->create();

        $this->expectException(LogicException::class);
        $role->update(['is_active' => false]);
    }

    public function test_super_admin_role_takes_no_explicit_permissions(): void
    {
        $role = Role::factory()->superAdmin()->create();

        $this->expectException(LogicException::class);
        $role->syncPermissions(['clients.view']);
    }
}
