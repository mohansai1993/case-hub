<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlanResource;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Storage plans a client can subscribe to - not relevant to lawyers. */
class PlanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! $request->user('sanctum')->isClient()) {
            return response()->json(['message' => 'Only clients can view subscription plans.'], 403);
        }

        $plans = Plan::where('is_active', true)->orderByDesc('is_popular')->orderBy('name')->get();

        return response()->json(['data' => PlanResource::collection($plans)]);
    }
}
