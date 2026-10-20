<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Cases\UploadDocumentRequest;
use App\Http\Resources\CaseDocumentResource;
use App\Models\CaseDocument;
use App\Models\LegalCase;
use App\Services\Billing\StorageQuotaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class CaseDocumentController extends Controller
{
    public function __construct(private readonly StorageQuotaService $quota)
    {
    }

    public function index(Request $request, LegalCase $case): JsonResponse
    {
        $this->authorizeParticipant($request, $case);

        $documents = $case->documents()->oldest('created_at')->get();

        return response()->json(['data' => CaseDocumentResource::collection($documents)]);
    }

    public function store(UploadDocumentRequest $request, LegalCase $case): JsonResponse
    {
        $user = $request->user('sanctum');
        $this->authorizeParticipant($request, $case);

        $file = $request->file('file');
        $sizeBytes = $file->getSize();

        $document = DB::transaction(function () use ($case, $user, $file, $sizeBytes) {
            if ($user->isClient()) {
                // Lock the subscription row FIRST, before re-checking the quota: this
                // serializes concurrent uploads from the same client so the second one
                // re-reads "used bytes" only after the first's insert has committed,
                // instead of both reading a stale sum and both passing.
                $case->client->subscription()->lockForUpdate()->first();

                if (! $this->quota->canUpload($case->client, $sizeBytes)) {
                    return null;
                }
            }

            $path = $file->store(
                config('casehub.case_documents.directory'),
                config('casehub.case_documents.disk'),
            );

            return CaseDocument::create([
                'case_id' => $case->id,
                'uploaded_by' => $user->user_id,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size_bytes' => $sizeBytes,
            ]);
        });

        if ($document === null) {
            return response()->json([
                'message' => 'You do not have enough storage for this file. Upgrade your plan to continue.',
                'code' => 'storage_full',
            ], 422);
        }

        return response()->json(['data' => new CaseDocumentResource($document)], 201);
    }

    public function download(Request $request, LegalCase $case, CaseDocument $document): Response
    {
        $this->authorizeParticipant($request, $case);

        abort_unless($document->case_id === $case->id, 404);
        abort_if(! $document->isAccessible(), 410, 'This file is no longer accessible.');

        Log::info("[audit:document] user {$request->user('sanctum')->user_id} downloaded document {$document->id} (case {$case->id})");

        return Storage::disk(config('casehub.case_documents.disk'))
            ->download($document->path, $document->original_name);
    }

    private function authorizeParticipant(Request $request, LegalCase $case): void
    {
        abort_unless($case->isParticipant($request->user('sanctum')), 403);
    }
}
