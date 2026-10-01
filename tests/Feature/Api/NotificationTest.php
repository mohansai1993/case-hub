<?php

namespace Tests\Feature\Api;

use App\Enums\CaseStatus;
use App\Models\LegalCase;
use App\Models\User;

class NotificationTest extends ApiTestCase
{
    private const INDEX = '/api/v1/notifications';

    private function openCase(User $client, User $advocate): LegalCase
    {
        return LegalCase::create([
            'client_id' => $client->user_id,
            'advocate_id' => $advocate->user_id,
            'title' => 'Property dispute',
            'status' => CaseStatus::Accepted,
        ]);
    }

    private function sendMessage(User $sender, LegalCase $case, string $body = 'Hello'): void
    {
        $this->postJson("/api/v1/cases/{$case->id}/messages", ['body' => $body], $this->bearer($sender))->assertCreated();

        // The test app would otherwise reuse the resolved user from this call.
        $this->app['auth']->forgetGuards();
    }

    public function test_guests_cannot_reach_notifications(): void
    {
        $this->getJson(self::INDEX)->assertStatus(401);
    }

    public function test_a_client_sees_a_notification_when_the_lawyer_messages_them(): void
    {
        $client = User::factory()->create();
        $lawyer = User::factory()->lawyer()->create(['name' => 'Adv. Rao']);
        $case = $this->openCase($client, $lawyer);

        $this->sendMessage($lawyer, $case, 'Please share the documents');

        $response = $this->getJson(self::INDEX, $this->bearer($client))->assertOk();

        $response->assertJsonPath('meta.unread_count', 1);
        $response->assertJsonPath('data.0.title', 'Adv. Rao');
        $response->assertJsonPath('data.0.body', 'Please share the documents');
        $response->assertJsonPath('data.0.read', false);
        $response->assertJsonPath('data.0.data.case_id', $case->id);
    }

    public function test_a_lawyer_sees_a_notification_when_the_client_messages_them(): void
    {
        $client = User::factory()->create(['name' => 'Rahul Sharma']);
        $lawyer = User::factory()->lawyer()->create();
        $case = $this->openCase($client, $lawyer);

        $this->sendMessage($client, $case, 'When can we talk?');

        $this->getJson(self::INDEX, $this->bearer($lawyer))
            ->assertOk()
            ->assertJsonPath('meta.unread_count', 1)
            ->assertJsonPath('data.0.title', 'Rahul Sharma');
    }

    public function test_the_sender_does_not_notify_themselves(): void
    {
        $client = User::factory()->create();
        $lawyer = User::factory()->lawyer()->create();
        $case = $this->openCase($client, $lawyer);

        $this->sendMessage($client, $case);

        $this->getJson(self::INDEX, $this->bearer($client))->assertJsonPath('meta.unread_count', 0);
    }

    public function test_pagination_meta_is_present(): void
    {
        $user = User::factory()->create();

        $this->getJson(self::INDEX, $this->bearer($user))
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['current_page', 'last_page', 'unread_count']]);
    }

    public function test_a_notification_can_be_marked_read(): void
    {
        $client = User::factory()->create();
        $lawyer = User::factory()->lawyer()->create();
        $case = $this->openCase($client, $lawyer);
        $this->sendMessage($lawyer, $case);

        $notification = $client->notifications()->first();

        $this->postJson(self::INDEX . "/{$notification->id}/read", [], $this->bearer($client))
            ->assertOk()->assertJsonPath('message', 'Marked as read.');

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_a_user_cannot_mark_another_users_notification_read(): void
    {
        $client = User::factory()->create();
        $lawyer = User::factory()->lawyer()->create();
        $case = $this->openCase($client, $lawyer);
        $this->sendMessage($lawyer, $case);

        $notification = $client->notifications()->first();
        $intruder = User::factory()->create();

        $this->postJson(self::INDEX . "/{$notification->id}/read", [], $this->bearer($intruder))->assertNotFound();
        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_all_notifications_can_be_marked_read_at_once(): void
    {
        $client = User::factory()->create();
        $lawyer = User::factory()->lawyer()->create();
        $case = $this->openCase($client, $lawyer);
        $this->sendMessage($lawyer, $case, 'one');
        $this->sendMessage($lawyer, $case, 'two');

        $this->assertSame(2, $client->unreadNotifications()->count());

        $this->postJson(self::INDEX . '/read-all', [], $this->bearer($client))
            ->assertOk()->assertJsonPath('message', 'All notifications marked as read.');

        $this->assertSame(0, $client->fresh()->unreadNotifications()->count());
    }
}
