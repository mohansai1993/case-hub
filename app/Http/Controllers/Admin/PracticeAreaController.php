<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PracticeAreaRequest;
use App\Models\PracticeArea;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * The "Practice areas / specialization" chips lawyers pick from at
 * registration (see Api\V1\PracticeAreaController) - kept editable here
 * instead of being fixed at seed time.
 */
class PracticeAreaController extends Controller
{
    public function index(): View
    {
        return view('admin.practice-areas', [
            'practiceAreas' => PracticeArea::withCount('lawyers')->orderBy('name')->get(),
        ]);
    }

    public function store(PracticeAreaRequest $request): JsonResponse
    {
        $area = PracticeArea::create([
            'name' => $request->name(),
            'slug' => $this->uniqueSlug($request->name()),
            'is_active' => true,
        ]);

        return response()->json(['message' => 'Specialization added.', 'data' => $area], 201);
    }

    public function update(PracticeAreaRequest $request, PracticeArea $practiceArea): JsonResponse
    {
        $practiceArea->update([
            'name' => $request->name(),
            'slug' => $this->uniqueSlug($request->name(), $practiceArea->id),
        ]);

        return response()->json(['message' => 'Specialization updated.', 'data' => $practiceArea]);
    }

    public function toggle(PracticeArea $practiceArea): JsonResponse
    {
        $practiceArea->update(['is_active' => ! $practiceArea->is_active]);

        return response()->json([
            'message' => $practiceArea->is_active ? 'Specialization activated.' : 'Specialization deactivated.',
            'data' => ['is_active' => $practiceArea->is_active],
        ]);
    }

    public function destroy(PracticeArea $practiceArea): JsonResponse
    {
        if ($practiceArea->lawyers()->exists()) {
            return response()->json([
                'message' => 'This specialization is assigned to lawyers and cannot be deleted. Deactivate it instead.',
            ], 422);
        }

        $practiceArea->delete();

        return response()->json(['message' => 'Specialization deleted.']);
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;

        while (
            PracticeArea::where('slug', $slug)
                ->when($ignoreId, fn ($query, $id) => $query->where('id', '!=', $id))
                ->exists()
        ) {
            $slug = $base . '-' . $suffix++;
        }

        return $slug;
    }
}
