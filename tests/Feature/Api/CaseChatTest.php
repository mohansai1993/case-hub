<?php

namespace Tests\Feature\Api;

use App\Enums\CaseStatus;
use App\Events\MessageSent;
use App\Models\LegalCase;
use App\Models\Plan;
use App\Models\PracticeArea;
use App\Models\User;
use App\Notifications\NewCaseMessage;
use App\Services\Billing\SubscriptionService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

class CaseChatTest extends ApiTestCase
{
    /** Submitting a case (with evidence) requires an active storage plan. */
    private function givePlan(User $client): void
    {
        app(SubscriptionService::class)->subscribe($client, Plan::factory()->create());
    }

    /** A case already past intake - advocate assigned, chat-ready. */
    private function openCase(User $client, User $advocate, string $status = 'accepted'): LegalCase
    {
        return LegalCase::create([
            'client_id' => $client->user_id,
            'advocate_id' => $advocate->user_id,
            'title' => 'Property dispute',
            'status' => CaseStatus::from($status),
            'submitted_at' => now(),
        ]);
    }

    // ---- Starting and submitting a case ----------------------------------------------

    public function test_a_client_can_start_and_submit_a_case_draft(): void
    {
        $client = User::factory()->create();
        $this->givePlan($client);
        $area = PracticeArea::create(['slug' => 'labour', 'name' => 'Labour Law', 'is_active' => true]);

        $draft = $this->postJson('/api/v1/cases', ['title' => 'Wrongful termination'], $this->bearer($client))
            ->assertCreated()
            ->assertJsonPath('data.is_draft', true)
            ->assertJsonPath('data.client.id', $client->user_id)
            ->json('data.id');

        // Invisible until submitted.
        $this->getJson('/api/v1/cases', $this->bearer($client))->assertJsonCount(0, 'data');

        $this->postJson("/api/v1/cases/{$draft}/submit", [
            'title' => 'Wrongful termination',
            'practice_area_id' => $area->id,
            'description' => 'Terminated without notice after 3 years.',
            'incident_date' => '2026-09-01',
            'location' => 'Mumbai, India',
        ], $this->bearer($client))
            ->assertOk()
            ->assertJsonPath('data.is_draft', false)
            ->assertJsonPath('data.practice_area.id', $area->id);

        $this->assertDatabaseHas('cases', ['id' => $draft, 'status' => 'pending', 'advocate_id' => null]);
        $this->getJson('/api/v1/cases', $this->bearer($client))->assertJsonCount(1, 'data');
    }

    public function test_a_lawyer_cannot_start_a_case(): void
    {
        $advocate = User::factory()->lawyer()->create();

        $this->postJson('/api/v1/cases', ['title' => 'x'], $this->bearer($advocate))
            ->assertForbidden();
    }

    public function test_a_client_without_a_storage_plan_cannot_start_a_case_draft(): void
    {
        $client = User::factory()->create();

        $this->postJson('/api/v1/cases', ['title' => 'x'], $this->bearer($client))
            ->assertStatus(422)->assertJsonPath('code', 'storage_full');

        $this->assertDatabaseCount('cases', 0);
    }

    public function test_submit_requires_a_valid_practice_area(): void
    {
        $client = User::factory()->create();
        $this->givePlan($client);
        $draft = $this->postJson('/api/v1/cases', [], $this->bearer($client))->json('data.id');

        $this->postJson("/api/v1/cases/{$draft}/submit", [
            'title' => 'x',
            'practice_area_id' => 999999,
            'description' => 'x',
            'incident_date' => '2026-01-01',
            'location' => 'x',
        ], $this->bearer($client))->assertUnprocessable()->assertJsonValidationErrors('practice_area_id');
    }

    public function test_a_client_can_discard_their_own_draft(): void
    {
        $client = User::factory()->create();
        $this->givePlan($client);
        $draft = $this->postJson('/api/v1/cases', ['title' => 'x'], $this->bearer($client))->json('data.id');

        $this->deleteJson("/api/v1/cases/{$draft}", [], $this->bearer($client))->assertOk();

        $this->assertDatabaseMissing('cases', ['id' => $draft]);
    }

    public function test_a_submitted_case_cannot_be_discarded_or_resubmitted(): void
    {
        $client = User::factory()->create();
        $advocate = User::factory()->lawyer()->create();
        $case = $this->openCase($client, $advocate, 'pending');

        $this->deleteJson("/api/v1/cases/{$case->id}", [], $this->bearer($client))->assertStatus(422);

        $this->postJson("/api/v1/cases/{$case->id}/submit", [
            'title' => 'x', 'practice_area_id' => PracticeArea::create(['slug' => 'x', 'name' => 'x', 'is_active' => true])->id,
            'description' => 'x', 'incident_date' => '2026-01-01', 'location' => 'x',
        ], $this->bearer($client))->assertStatus(422);
    }

    public function test_another_client_cannot_touch_someone_elses_draft(): void
    {
        $client = User::factory()->create();
        $other = User::factory()->create();
        $this->givePlan($client);
        $draft = $this->postJson('/api/v1/cases', ['title' => 'x'], $this->bearer($client))->json('data.id');
        $this->app['auth']->forgetGuards();

        $this->deleteJson("/api/v1/cases/{$draft}", [], $this->bearer($other))->assertForbidden();
    }

    // ---- Accept / reject ------------------------------------------------------------

    public function test_advocate_accepts_a_pending_case(): void
    {
        $client = User::factory()->create();
        $advocate = User::factory()->lawyer()->create();
        $case = $this->openCase($client, $advocate, 'pending');

        $this->postJson("/api/v1/cases/{$case->id}/accept", [], $this->bearer($advocate))
            ->assertOk()->assertJsonPath('data.status', 'accepted');

        $this->assertSame(CaseStatus::Accepted, $case->fresh()->status);
    }

    public function test_client_cannot_accept_their_own_case(): void
    {
        $client = User::factory()->create();
        $advocate = User::factory()->lawyer()->create();
        $case = $this->openCase($client, $advocate, 'pending');

        $this->postJson("/api/v1/cases/{$case->id}/accept", [], $this->bearer($client))->assertForbidden();
    }

    public function test_a_decided_case_cannot_be_decided_again(): void
    {
        $client = User::factory()->create();
        $advocate = User::factory()->lawyer()->create();
        $case = $this->openCase($client, $advocate, 'accepted');

        $this->postJson("/api/v1/cases/{$case->id}/accept", [], $this->bearer($advocate))->assertStatus(422);
        $this->postJson("/api/v1/cases/{$case->id}/reject", [], $this->bearer($advocate))->assertStatus(422);
    }

    // ---- Chat -----------------------------------------------------------------------

    public function test_messages_are_blocked_until_the_case_is_accepted(): void
    {
        $client = User::factory()->create();
        $advocate = User::factory()->lawyer()->create();
        $case = $this->openCase($client, $advocate, 'pending');

        $this->postJson("/api/v1/cases/{$case->id}/messages", ['body' => 'Hello'], $this->bearer($client))
            ->assertStatus(422);
    }

    public function test_client_and_advocate_can_chat_once_accepted(): void
    {
        Event::fake([MessageSent::class]);
        Notification::fake();

        $client = User::factory()->create();
        $advocate = User::factory()->lawyer()->create();
        $case = $this->openCase($client, $advocate);

        $response = $this->postJson("/api/v1/cases/{$case->id}/messages", ['body' => 'When is the hearing?'], $this->bearer($client))
            ->assertCreated()
            ->assertJsonPath('data.body', 'When is the hearing?')
            ->assertJsonPath('data.is_mine', true)
            ->assertJsonPath('data.read', false);

        $this->assertDatabaseHas('case_messages', [
            'case_id' => $case->id,
            'sender_id' => $client->user_id,
            'body' => 'When is the hearing?',
        ]);

        Event::assertDispatched(MessageSent::class, fn ($event) => $event->message->case_id === $case->id);
        Notification::assertSentTo($advocate, NewCaseMessage::class);
        Notification::assertNotSentTo($client, NewCaseMessage::class);
    }

    public function test_an_outsider_cannot_read_or_send_messages(): void
    {
        $client = User::factory()->create();
        $advocate = User::factory()->lawyer()->create();
        $outsider = User::factory()->create();
        $case = $this->openCase($client, $advocate);

        $this->getJson("/api/v1/cases/{$case->id}/messages", $this->bearer($outsider))->assertForbidden();
        $this->postJson("/api/v1/cases/{$case->id}/messages", ['body' => 'hi'], $this->bearer($outsider))->assertForbidden();
    }

    public function test_message_history_is_paginated_oldest_relation_and_marks_sender_correctly(): void
    {
        $client = User::factory()->create();
        $advocate = User::factory()->lawyer()->create();
        $case = $this->openCase($client, $advocate);

        $this->postJson("/api/v1/cases/{$case->id}/messages", ['body' => 'First'], $this->bearer($client))->assertCreated();
        $this->app['auth']->forgetGuards(); // otherwise Sanctum reuses the previously resolved user
        $this->postJson("/api/v1/cases/{$case->id}/messages", ['body' => 'Second'], $this->bearer($advocate))->assertCreated();
        $this->app['auth']->forgetGuards();

        $this->getJson("/api/v1/cases/{$case->id}/messages", $this->bearer($advocate))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.is_mine', true) // latest first: advocate's "Second"
            ->assertJsonPath('data.1.is_mine', false);
    }

    public function test_marking_read_only_affects_the_other_partys_messages(): void
    {
        $client = User::factory()->create();
        $advocate = User::factory()->lawyer()->create();
        $case = $this->openCase($client, $advocate);

        $this->postJson("/api/v1/cases/{$case->id}/messages", ['body' => 'From client'], $this->bearer($client));
        $this->app['auth']->forgetGuards();
        $this->postJson("/api/v1/cases/{$case->id}/messages", ['body' => 'From advocate'], $this->bearer($advocate));
        $this->app['auth']->forgetGuards();

        $this->postJson("/api/v1/cases/{$case->id}/messages/read", [], $this->bearer($advocate))->assertOk();

        $this->assertNotNull($case->messages()->where('sender_id', $client->user_id)->first()->read_at);
        $this->assertNull($case->messages()->where('sender_id', $advocate->user_id)->first()->read_at);
    }

    public function test_case_list_reports_unread_count_per_viewer(): void
    {
        $client = User::factory()->create();
        $advocate = User::factory()->lawyer()->create();
        $case = $this->openCase($client, $advocate);

        $this->postJson("/api/v1/cases/{$case->id}/messages", ['body' => 'Ping'], $this->bearer($client));
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/cases', $this->bearer($advocate))
            ->assertOk()->assertJsonPath('data.0.unread_count', 1);
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/cases', $this->bearer($client))
            ->assertOk()->assertJsonPath('data.0.unread_count', 0);
    }

    // ---- Channel authorization ---------------------------------------------------

    public function test_only_participants_pass_channel_authorization(): void
    {
        $client = User::factory()->create();
        $advocate = User::factory()->lawyer()->create();
        $outsider = User::factory()->create();
        $case = $this->openCase($client, $advocate);

        $this->assertTrue($case->isParticipant($client));
        $this->assertTrue($case->isParticipant($advocate));
        $this->assertFalse($case->isParticipant($outsider));
    }
}
