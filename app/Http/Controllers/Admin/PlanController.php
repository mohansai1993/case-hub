<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PlanRequest;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PlanController extends Controller
{
    public function index(): View
    {
        return view('admin.subscriptions', [
            'plans' => Plan::orderByDesc('is_popular')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.create-plan', ['plan' => null]);
    }

    public function store(PlanRequest $request): JsonResponse
    {
        $plan = DB::transaction(function () use ($request) {
            if ($request->isPopular()) {
                Plan::where('is_popular', true)->update(['is_popular' => false]);
            }

            return Plan::create([
                'name' => $request->name(),
                'storage_amount' => $request->integer('storage_amount'),
                'storage_unit' => $request->storageUnit(),
                'price' => $request->integer('price'),
                'description' => $request->description(),
                'is_popular' => $request->isPopular(),
                'is_active' => true,
            ]);
        });

        return response()->json(['message' => 'Plan created.', 'data' => ['id' => $plan->id]], 201);
    }

    public function edit(Plan $plan): View
    {
        return view('admin.create-plan', ['plan' => $plan]);
    }

    public function update(PlanRequest $request, Plan $plan): JsonResponse
    {
        DB::transaction(function () use ($request, $plan) {
            if ($request->isPopular()) {
                Plan::where('is_popular', true)->where('id', '!=', $plan->id)->update(['is_popular' => false]);
            }

            $plan->update([
                'name' => $request->name(),
                'storage_amount' => $request->integer('storage_amount'),
                'storage_unit' => $request->storageUnit(),
                'price' => $request->integer('price'),
                'description' => $request->description(),
                'is_popular' => $request->isPopular(),
            ]);
        });

        return response()->json(['message' => 'Plan updated.', 'data' => ['id' => $plan->id]]);
    }

    public function toggle(Plan $plan): JsonResponse
    {
        $plan->update(['is_active' => ! $plan->is_active]);

        return response()->json([
            'message' => $plan->is_active ? 'Plan activated.' : 'Plan deactivated.',
            'data' => ['is_active' => $plan->is_active],
        ]);
    }

    /** Body: { "ids": [1, 2, ...] } - the Settings page's "Bulk Deactivate". */
    public function bulkDeactivate(Request $request): JsonResponse
    {
        $ids = array_filter((array) $request->input('ids', []), fn ($id) => is_numeric($id));

        $count = Plan::whereIn('id', $ids)->update(['is_active' => false]);

        return response()->json(['message' => "{$count} plan(s) deactivated."]);
    }

    public function destroy(Plan $plan): JsonResponse
    {
        $plan->delete();

        return response()->json(['message' => 'Plan deleted.']);
    }
}
