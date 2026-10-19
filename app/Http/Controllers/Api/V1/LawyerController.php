<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserStatus;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The "Lawyers" tab - a general directory of every verified lawyer, not tied
 * to a specific case. For picking a lawyer against one case's practice area
 * instead, see CaseLawyerController.
 */
class LawyerController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user('sanctum')->isClient(), 403);

        $lawyers = User::lawyers()
            ->where('status', UserStatus::Active)
            ->whereHas('lawyerProfile', fn ($query) => $query->where('verification_status', VerificationStatus::Verified))
            ->when($request->filled('q'), fn ($query) => $query->where('name', 'like', '%' . addcslashes($request->string('q')->trim()->toString(), '%_\\') . '%'))
            ->when($request->filled('practice_area_id'), fn ($query) => $query->whereHas(
                'practiceAreas',
                fn ($areas) => $areas->where('practice_areas.id', $request->input('practice_area_id')),
            ))
            ->with('lawyerProfile', 'practiceAreas')
            ->paginate(self::PER_PAGE);

        return response()->json(['data' => UserResource::collection($lawyers)]);
    }
}
