<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PracticeArea;
use Illuminate\Http\JsonResponse;

class PracticeAreaController extends Controller
{
    /** Options for the "Practice areas / specialization" chips on lawyer registration. */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => PracticeArea::active()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
