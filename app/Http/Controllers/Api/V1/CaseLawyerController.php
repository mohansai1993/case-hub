<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserStatus;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CaseLawyerController extends Controller
{
    private const PER_PAGE = 20;

    /** Verified, active lawyers who handle this case's practice area - the list the "assign a lawyer" screen shows. */
    public function index(Request $request, LegalCase $case): JsonResponse
    {
        abort_unless($case->client_id === $request->user('sanctum')->user_id, 403);

        if ($case->isDraft()) {
            return response()->json(['message' => 'Submit the case before browsing lawyers.'], 422);
        }

        $lawyers = User::lawyers()
            ->where('status', UserStatus::Active)
            ->whereHas('lawyerProfile', fn ($query) => $query->where('verification_status', VerificationStatus::Verified))
            ->whereHas('practiceAreas', fn ($query) => $query->where('practice_areas.id', $case->practice_area_id))
            ->with('lawyerProfile', 'practiceAreas')
            ->paginate(self::PER_PAGE);

        return response()->json(['data' => UserResource::collection($lawyers)]);
    }
}
