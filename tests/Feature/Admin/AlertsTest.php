<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\User;
use App\Services\Admin\AdminAlertDispatcher;
use Database\Seeders\PracticeAreaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertsTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Password@123';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PracticeAreaSeeder::class);
    }

    private function asAdmin(Admin $admin): static
    {
        return $this->actingAs($admin, 'admin');
    }

    private function registerClient(array $override = []): void
    {
        $this->postJson('/api/v1/auth/register/client', array_merge([
            'name' => 'Rahul Sharma',
            'email' => 'rahul@example.com',
            'mobile' => '9876543210',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
            'terms_accepted' => true,
        ], $override))->assertCreated();
    }

    // ---- Registration fires the right admins -----------------------------

    public function test_a_new_client_registration_notifies_admins_who_can_view_clients(): void
    {
        $canSee = Admin::factory()->withPermissions(['clients.view'])->create();
        $cannotSee = Admin::factory()->withPermissions(['lawyers.view'])->create();
        $super = Admin::factory()->superAdmin()->create();

        $this->registerClient();

        $user = User::firstWhere('email', 'rahul@example.com');

        $this->assertSame(1, $canSee->notifications()->count());
        $this->assertSame(0, $cannotSee->notifications()->count());
        $this->assertSame(1, $super->notifications()->count());

        $data = $canSee->notifications()->first()->data;
        $this->assertSame('New client registered', $data['title']);
        $this->assertStringContainsString('Rahul Sharma', $data['body']);
        $this->assertSame(route('admin.client-details', $user->user_id), $data['action_url']);
    }

    public function test_an_inactive_admin_is_not_notified(): void
    {
        $inactive = Admin::factory()->withPermissions(['clients.view'])->inactive()->create();

        $this->registerClient();

        $this->assertSame(0, $inactive->notifications()->count());
    }

    public function test_a_failed_alert_does_not_break_registration(): void
    {
        $dispatcher = \Mockery::mock(AdminAlertDispatcher::class);
        $dispatcher->shouldReceive('newAccountRegistered')->andThrow(new \RuntimeException('boom'));
        $this->app->instance(AdminAlertDispatcher::class, $dispatcher);

        $this->registerClient();

        $this->assertNotNull(User::firstWhere('email', 'rahul@example.com'));
    }

    // ---- Bell icon endpoints -----------------------------------------------

    public function test_guests_cannot_reach_alerts(): void
    {
        $this->getJson(route('admin.alerts.index'))->assertUnauthorized();
    }

    public function test_the_bell_lists_an_admins_own_notifications_and_unread_count(): void
    {
        $admin = Admin::factory()->withPermissions(['clients.view'])->create();
        $this->asAdmin($admin);

        $this->registerClient();

        $response = $this->getJson(route('admin.alerts.index'))->assertOk();
        $response->assertJsonPath('unread_count', 1);
        $response->assertJsonPath('notifications.0.title', 'New client registered');
        $response->assertJsonPath('notifications.0.read', false);
    }

    public function test_admins_only_see_their_own_notifications(): void
    {
        $admin = Admin::factory()->withPermissions(['clients.view'])->create();
        $other = Admin::factory()->withPermissions(['clients.view'])->create();

        $this->registerClient();

        $this->asAdmin($other)->getJson(route('admin.alerts.index'))
            ->assertJsonPath('unread_count', 1);

        // Marking one admin's copy read must not affect the other's.
        $notificationId = $other->notifications()->first()->id;
        $this->postJson(route('admin.alerts.read', $notificationId))->assertOk();

        $this->assertSame(0, $other->fresh()->unreadNotifications()->count());
        $this->assertSame(1, $admin->fresh()->unreadNotifications()->count());
    }

    public function test_a_notification_can_be_marked_read(): void
    {
        $admin = Admin::factory()->withPermissions(['clients.view'])->create();
        $this->registerClient();

        $notification = $admin->notifications()->first();

        $this->asAdmin($admin)->postJson(route('admin.alerts.read', $notification->id))->assertOk();

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_an_admin_cannot_mark_another_admins_notification_read(): void
    {
        $admin = Admin::factory()->withPermissions(['clients.view'])->create();
        $intruder = Admin::factory()->withPermissions(['clients.view'])->create();
        $this->registerClient();

        $notification = $admin->notifications()->first();

        $this->asAdmin($intruder)->postJson(route('admin.alerts.read', $notification->id))->assertNotFound();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_all_notifications_can_be_marked_read_at_once(): void
    {
        $admin = Admin::factory()->withPermissions(['clients.view'])->create();
        $this->registerClient(['email' => 'one@example.com', 'mobile' => '9876543211']);
        $this->registerClient(['email' => 'two@example.com', 'mobile' => '9876543212']);

        $this->assertSame(2, $admin->fresh()->unreadNotifications()->count());

        $this->asAdmin($admin)->postJson(route('admin.alerts.read-all'))->assertOk();

        $this->assertSame(0, $admin->fresh()->unreadNotifications()->count());
    }
}
