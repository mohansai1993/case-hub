<?php

namespace Tests\Feature\Admin;

use App\Enums\UserStatus;
use App\Enums\VerificationStatus;
use App\Models\AccountAction;
use App\Models\Admin;
use App\Models\PracticeArea;
use App\Models\User;
use Database\Seeders\PracticeAreaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountModerationTest extends TestCase
{
    use RefreshDatabase;

    private Admin $super;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PracticeAreaSeeder::class);
        $this->super = Admin::factory()->superAdmin()->create();
    }

    private function asSuper(): static
    {
        return $this->actingAs($this->super, 'admin');
    }

    private function asStaff(array $permissions): static
    {
        return $this->actingAs(Admin::factory()->withPermissions($permissions)->create(), 'admin');
    }

    // ---- Lists --------------------------------------------------------------

    public function test_client_list_shows_only_clients(): void
    {
        User::factory()->create(['name' => 'Rahul Client']);
        User::factory()->lawyer()->create(['name' => 'Sarah Lawyer']);

        $this->asSuper()->get(route('admin.clients'))
            ->assertOk()
            ->assertSee('Rahul Client')
            ->assertDontSee('Sarah Lawyer');
    }

    public function test_client_list_searches_by_name_email_and_mobile(): void
    {
        User::factory()->create(['name' => 'Rahul Sharma', 'email' => 'rahul@example.com', 'mobile' => '9876543210']);
        User::factory()->create(['name' => 'Priya Verma', 'email' => 'priya@example.com', 'mobile' => '9123456780']);

        foreach (['rahul', 'RAHUL@example', '98765'] as $term) {
            $this->asSuper()->get(route('admin.clients', ['q' => $term]))
                ->assertSee('Rahul Sharma')->assertDontSee('Priya Verma');
        }
    }

    public function test_search_treats_like_wildcards_literally(): void
    {
        User::factory()->create(['name' => 'Rahul Sharma']);

        $this->asSuper()->get(route('admin.clients', ['q' => '%']))
            ->assertOk()->assertDontSee('Rahul Sharma')->assertSee('No clients match your search.');
    }

    public function test_client_list_filters_by_status_and_ignores_garbage(): void
    {
        User::factory()->create(['name' => 'Active Person']);
        User::factory()->suspended()->create(['name' => 'Suspended Person']);

        $this->asSuper()->get(route('admin.clients', ['status' => 'suspended']))
            ->assertSee('Suspended Person')->assertDontSee('Active Person');

        $this->asSuper()->get(route('admin.clients', ['status' => 'nonsense', 'practice_area' => ['x']]))
            ->assertOk()->assertSee('Suspended Person')->assertSee('Active Person');
    }

    public function test_client_list_paginates(): void
    {
        User::factory()->count(17)->create();

        $this->asSuper()->get(route('admin.clients'))
            ->assertSee('Showing 1 to 15 of 17 registered clients')
            ->assertSee('page=2', false);

        $this->asSuper()->get(route('admin.clients', ['page' => 2]))
            ->assertSee('Showing 16 to 17 of 17 registered clients');
    }

    public function test_lawyer_list_filters_by_specialization(): void
    {
        $severance = PracticeArea::firstWhere('slug', 'severance');
        $unrelated = User::factory()->lawyer()->create(['name' => 'Unrelated Lawyer']);
        $matched = User::factory()->lawyer()->create(['name' => 'Severance Lawyer']);
        $matched->practiceAreas()->attach($severance->id);

        $this->asSuper()->get(route('admin.lawyers', ['practice_area' => $severance->id]))
            ->assertSee('Severance Lawyer')->assertSee('Severance')->assertDontSee('Unrelated Lawyer');
    }

    public function test_lists_and_details_need_the_view_permission(): void
    {
        $client = User::factory()->create();

        $this->asStaff(['lawyers.view'])->get(route('admin.clients'))->assertForbidden();
        $this->asStaff(['lawyers.view'])->get(route('admin.client-details', $client->user_id))->assertForbidden();
        $this->asStaff(['clients.view'])->get(route('admin.lawyers'))->assertForbidden();
        $this->asStaff(['clients.view'])->get(route('admin.clients'))->assertOk();
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('admin.clients'))->assertRedirect(route('login'));
        $this->postJson(route('admin.clients.suspend', User::factory()->create()->user_id), ['reason' => 'x'])->assertUnauthorized();
    }

    // ---- Detail pages ------------------------------------------------------------

    public function test_a_client_cannot_be_opened_through_the_lawyer_url_and_vice_versa(): void
    {
        $client = User::factory()->create();
        $lawyer = User::factory()->lawyer()->create();

        $this->asSuper()->get(route('admin.lawyer-details', $client->user_id))->assertNotFound();
        $this->asSuper()->get(route('admin.client-details', $lawyer->user_id))->assertNotFound();
        $this->asSuper()->get('/admin/clients/not-a-uuid')->assertNotFound();
    }

    public function test_client_page_offers_the_buttons_that_fit_the_state_and_permission(): void
    {
        $client = User::factory()->create();

        $active = $this->asSuper()->get(route('admin.client-details', $client->user_id))->assertOk();
        $active->assertSee('data-moderate="suspend"', false)->assertDontSee('data-moderate="activate"', false);

        $client->forceFill(['status' => UserStatus::Suspended])->save();
        $this->asSuper()->get(route('admin.client-details', $client->user_id))
            ->assertSee('data-moderate="activate"', false)->assertDontSee('data-moderate="suspend"', false);

        $this->asStaff(['clients.view'])->get(route('admin.client-details', $client->user_id))
            ->assertOk()->assertDontSee('data-moderate', false)->assertSee('do not have permission');
    }

    public function test_pages_use_sweetalert_and_no_native_dialogs(): void
    {
        $lawyer = User::factory()->lawyer()->create();

        $page = $this->asSuper()->get(route('admin.lawyer-details', $lawyer->user_id))->assertOk();

        $page->assertSee('sweetalert2', false)->assertDontSee('confirm(', false)->assertDontSee('alert(', false);
        $this->assertStringNotContainsString('confirm(', file_get_contents(public_path('assets/admin/moderation.js')));
        $this->assertStringNotContainsString(' alert(', file_get_contents(public_path('assets/admin/moderation.js')));
    }

    // ---- Suspend / activate --------------------------------------------------------

    public function test_suspending_a_client_blocks_them_everywhere_and_is_audited(): void
    {
        $client = User::factory()->create(['email' => 'rahul@example.com']);
        $client->createToken('phone');
        $client->createToken('tablet');

        $this->asSuper()->postJson(route('admin.clients.suspend', $client->user_id), ['reason' => 'Fraudulent documents'])
            ->assertOk()
            ->assertJsonPath('data.status', 'suspended');

        $this->assertSame(UserStatus::Suspended, $client->fresh()->status);
        $this->assertSame(0, $client->tokens()->count());

        $log = AccountAction::firstWhere('user_id', $client->user_id);
        $this->assertSame('suspended', $log->action);
        $this->assertSame('active', $log->from_status);
        $this->assertSame('suspended', $log->to_status);
        $this->assertSame('Fraudulent documents', $log->reason);
        $this->assertSame($this->super->id, $log->admin_id);

        $this->postJson('/api/v1/auth/login', ['type' => 'client', 'identifier' => 'rahul@example.com', 'password' => 'Password@123'])
            ->assertStatus(403)->assertJsonPath('code', 'account_inactive');
    }

    public function test_suspending_requires_a_real_reason(): void
    {
        $client = User::factory()->create();

        foreach ([[], ['reason' => ''], ['reason' => 'ab'], ['reason' => str_repeat('a', 501)]] as $body) {
            $this->asSuper()->postJson(route('admin.clients.suspend', $client->user_id), $body)
                ->assertUnprocessable()->assertJsonValidationErrors('reason');
        }

        $this->assertSame(UserStatus::Active, $client->fresh()->status);
        $this->assertDatabaseCount('account_actions', 0);
    }

    public function test_suspending_twice_is_rejected_cleanly(): void
    {
        $client = User::factory()->suspended()->create();

        $this->asSuper()->postJson(route('admin.clients.suspend', $client->user_id), ['reason' => 'again please'])
            ->assertUnprocessable()->assertJsonPath('message', 'This account is already suspended.');

        $this->assertDatabaseCount('account_actions', 0);
    }

    public function test_activating_restores_access(): void
    {
        $client = User::factory()->suspended()->create(['email' => 'rahul@example.com']);

        $this->asSuper()->postJson(route('admin.clients.activate', $client->user_id))
            ->assertOk()->assertJsonPath('data.status', 'active');

        $this->assertSame(UserStatus::Active, $client->fresh()->status);
        $this->postJson('/api/v1/auth/login', ['type' => 'client', 'identifier' => 'rahul@example.com', 'password' => 'Password@123'])->assertOk();

        $this->assertSame('activated', AccountAction::firstWhere('user_id', $client->user_id)->action);
    }

    public function test_an_inactive_account_can_be_activated_and_an_active_one_cannot(): void
    {
        $inactive = User::factory()->inactive()->create();
        $active = User::factory()->create();

        $this->asSuper()->postJson(route('admin.clients.activate', $inactive->user_id))->assertOk();
        $this->asSuper()->postJson(route('admin.clients.activate', $active->user_id))
            ->assertUnprocessable()->assertJsonPath('message', 'This account is already active.');
    }

    public function test_lawyers_can_be_suspended_and_activated_too(): void
    {
        $lawyer = User::factory()->lawyer()->create();

        $this->asSuper()->postJson(route('admin.lawyers.suspend', $lawyer->user_id), ['reason' => 'Bar council complaint'])->assertOk();
        $this->assertSame(UserStatus::Suspended, $lawyer->fresh()->status);

        $this->asSuper()->postJson(route('admin.lawyers.activate', $lawyer->user_id))->assertOk();
        $this->assertSame(UserStatus::Active, $lawyer->fresh()->status);
    }

    public function test_the_type_in_the_url_must_match_the_account(): void
    {
        $client = User::factory()->create();
        $lawyer = User::factory()->lawyer()->create();

        $this->asSuper()->postJson(route('admin.lawyers.suspend', $client->user_id), ['reason' => 'wrong door'])->assertNotFound();
        $this->asSuper()->postJson(route('admin.clients.suspend', $lawyer->user_id), ['reason' => 'wrong door'])->assertNotFound();

        $this->assertSame(UserStatus::Active, $client->fresh()->status);
        $this->assertSame(UserStatus::Active, $lawyer->fresh()->status);
    }

    public function test_unknown_account_is_a_404(): void
    {
        $this->asSuper()->postJson(route('admin.clients.suspend', '11111111-1111-4111-8111-111111111111'), ['reason' => 'nobody'])->assertNotFound();
    }

    public function test_admin_accounts_cannot_be_reached_through_these_endpoints(): void
    {
        $this->asSuper()->postJson(route('admin.clients.suspend', $this->super->id), ['reason' => 'nope'])->assertNotFound();
    }

    // ---- Permissions ------------------------------------------------------------------

    public function test_viewing_does_not_allow_suspending(): void
    {
        $client = User::factory()->create();

        $this->asStaff(['clients.view'])->postJson(route('admin.clients.suspend', $client->user_id), ['reason' => 'because'])->assertForbidden();
        $this->assertSame(UserStatus::Active, $client->fresh()->status);
    }

    public function test_client_permissions_do_not_reach_lawyers_and_vice_versa(): void
    {
        $client = User::factory()->create();
        $lawyer = User::factory()->lawyer()->create();

        $this->asStaff(['clients.update'])->postJson(route('admin.lawyers.suspend', $lawyer->user_id), ['reason' => 'because'])->assertForbidden();
        $this->asStaff(['lawyers.update'])->postJson(route('admin.clients.suspend', $client->user_id), ['reason' => 'because'])->assertForbidden();

        $this->asStaff(['clients.update'])->postJson(route('admin.clients.suspend', $client->user_id), ['reason' => 'because'])->assertOk();
    }

    // ---- Lawyer verification --------------------------------------------------------------

    public function test_suspending_a_lawyer_does_not_change_their_verification(): void
    {
        $lawyer = User::factory()->lawyer(VerificationStatus::Verified)->create();

        $this->asSuper()->postJson(route('admin.lawyers.suspend', $lawyer->user_id), ['reason' => 'under review'])->assertOk();

        $this->assertSame(VerificationStatus::Verified, $lawyer->fresh()->lawyerProfile->verification_status);
    }

    public function test_history_shows_reasons_and_who_decided(): void
    {
        $client = User::factory()->create();
        $this->asSuper()->postJson(route('admin.clients.suspend', $client->user_id), ['reason' => 'Chargeback abuse']);

        $this->asSuper()->get(route('admin.client-details', $client->user_id))
            ->assertSee('Account suspended')
            ->assertSee('Chargeback abuse')
            ->assertSee($this->super->name);
    }
}
