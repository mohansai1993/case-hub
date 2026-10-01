<?php

namespace Tests\Feature\Api;

use App\Enums\CaseStatus;
use App\Models\CaseDocument;
use App\Models\LegalCase;
use App\Models\Plan;
use App\Models\User;
use App\Services\Billing\SubscriptionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class CaseDocumentTest extends ApiTestCase
{
    private function givePlan(User $client, ?Plan $plan = null): Plan
    {
        $plan ??= Plan::factory()->create(['storage_amount' => 5, 'storage_unit' => 'MB']);
        app(SubscriptionService::class)->subscribe($client, $plan);

        return $plan;
    }

    private function openCase(User $client, User $advocate): LegalCase
    {
        return LegalCase::create([
            'client_id' => $client->user_id,
            'advocate_id' => $advocate->user_id,
            'title' => 'x',
            'status' => CaseStatus::Accepted,
        ]);
    }

    // ---- Access ---------------------------------------------------------------

    public function test_an_outsider_cannot_list_upload_or_download(): void
    {
        Storage::fake('local');
        $client = User::factory()->create();
        $advocate = User::factory()->lawyer()->create();
        $outsider = User::factory()->create();
        $this->givePlan($client);
        $case = $this->openCase($client, $advocate);

        $this->getJson("/api/v1/cases/{$case->id}/documents", $this->bearer($outsider))->assertForbidden();
        $this->post("/api/v1/cases/{$case->id}/documents", ['file' => UploadedFile::fake()->create('a.pdf', 100, 'application/pdf')], array_merge($this->bearer($outsider), ['Accept' => 'application/json']))->assertForbidden();
    }

    // ---- Upload ---------------------------------------------------------------

    public function test_a_participant_can_upload_a_document(): void
    {
        Storage::fake('local');
        $client = User::factory()->create();
        $advocate = User::factory()->lawyer()->create();
        $this->givePlan($client);
        $case = $this->openCase($client, $advocate);

        $response = $this->post("/api/v1/cases/{$case->id}/documents", [
            'file' => UploadedFile::fake()->create('evidence.pdf', 100, 'application/pdf'),
        ], array_merge($this->bearer($client), ['Accept' => 'application/json']))->assertCreated();

        $response->assertJsonPath('data.original_name', 'evidence.pdf');
        $response->assertJsonPath('data.accessible', true);
        $this->assertDatabaseHas('case_documents', ['case_id' => $case->id, 'original_name' => 'evidence.pdf']);

        $document = CaseDocument::first();
        Storage::disk('local')->assertExists($document->path);
    }

    public function test_the_advocate_can_also_upload_to_the_case(): void
    {
        Storage::fake('local');
        $client = User::factory()->create();
        $advocate = User::factory()->lawyer()->create();
        $this->givePlan($client);
        $case = $this->openCase($client, $advocate);

        $this->post("/api/v1/cases/{$case->id}/documents", [
            'file' => UploadedFile::fake()->create('note.pdf', 50, 'application/pdf'),
        ], array_merge($this->bearer($advocate), ['Accept' => 'application/json']))->assertCreated();
    }

    public function test_upload_is_blocked_once_storage_is_full(): void
    {
        Storage::fake('local');
        $client = User::factory()->create();
        $advocate = User::factory()->lawyer()->create();
        $this->givePlan($client, Plan::factory()->create(['storage_amount' => 1, 'storage_unit' => 'MB']));
        $case = $this->openCase($client, $advocate);

        // Fill the 1MB quota.
        CaseDocument::create([
            'case_id' => $case->id, 'uploaded_by' => $client->user_id, 'path' => 'x',
            'original_name' => 'x.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 1024 * 1024,
        ]);

        $this->post("/api/v1/cases/{$case->id}/documents", [
            'file' => UploadedFile::fake()->create('too-big.pdf', 10, 'application/pdf'),
        ], array_merge($this->bearer($client), ['Accept' => 'application/json']))
            ->assertStatus(422)->assertJsonPath('code', 'storage_full');
    }

    public function test_only_allowed_file_types_are_accepted(): void
    {
        Storage::fake('local');
        $client = User::factory()->create();
        $advocate = User::factory()->lawyer()->create();
        $this->givePlan($client);
        $case = $this->openCase($client, $advocate);

        $this->post("/api/v1/cases/{$case->id}/documents", [
            'file' => UploadedFile::fake()->create('script.exe', 10, 'application/octet-stream'),
        ], array_merge($this->bearer($client), ['Accept' => 'application/json']))
            ->assertUnprocessable()->assertJsonValidationErrors('file');
    }

    // ---- List / download --------------------------------------------------------

    public function test_participants_can_list_documents(): void
    {
        Storage::fake('local');
        $client = User::factory()->create();
        $advocate = User::factory()->lawyer()->create();
        $this->givePlan($client);
        $case = $this->openCase($client, $advocate);
        CaseDocument::create(['case_id' => $case->id, 'uploaded_by' => $client->user_id, 'path' => 'a', 'original_name' => 'a.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 100]);

        $this->getJson("/api/v1/cases/{$case->id}/documents", $this->bearer($advocate))
            ->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_a_participant_can_download_an_accessible_document(): void
    {
        Storage::fake('local');
        $path = UploadedFile::fake()->create('a.pdf', 10)->store('case-documents', 'local');
        $client = User::factory()->create();
        $advocate = User::factory()->lawyer()->create();
        $this->givePlan($client);
        $case = $this->openCase($client, $advocate);
        $document = CaseDocument::create(['case_id' => $case->id, 'uploaded_by' => $client->user_id, 'path' => $path, 'original_name' => 'a.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 10240]);

        $this->get("/api/v1/cases/{$case->id}/documents/{$document->id}/download", $this->bearer($advocate))
            ->assertOk();
    }

    public function test_an_inaccessible_document_cannot_be_downloaded(): void
    {
        Storage::fake('local');
        $path = UploadedFile::fake()->create('a.pdf', 10)->store('case-documents', 'local');
        $client = User::factory()->create();
        $advocate = User::factory()->lawyer()->create();
        $this->givePlan($client);
        $case = $this->openCase($client, $advocate);
        $document = CaseDocument::create(['case_id' => $case->id, 'uploaded_by' => $client->user_id, 'path' => $path, 'original_name' => 'a.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 10240]);
        $document->forceFill(['inaccessible_at' => now()])->save();

        $this->get("/api/v1/cases/{$case->id}/documents/{$document->id}/download", $this->bearer($advocate))
            ->assertStatus(410);
    }

    public function test_a_document_from_another_case_404s(): void
    {
        Storage::fake('local');
        $client = User::factory()->create();
        $advocate = User::factory()->lawyer()->create();
        $this->givePlan($client);
        $caseA = $this->openCase($client, $advocate);
        $caseB = $this->openCase($client, $advocate);
        $document = CaseDocument::create(['case_id' => $caseA->id, 'uploaded_by' => $client->user_id, 'path' => 'a', 'original_name' => 'a.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 10]);

        $this->get("/api/v1/cases/{$caseB->id}/documents/{$document->id}/download", $this->bearer($client))
            ->assertNotFound();
    }
}
