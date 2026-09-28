<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AccountListRequest;
use App\Models\PracticeArea;
use App\Models\User;
use Illuminate\View\View;

class LawyerController extends Controller
{
    private const PER_PAGE = 15;

    public function index(AccountListRequest $request): View
    {
        $lawyers = User::lawyers()
            ->with('lawyerProfile', 'practiceAreas')
            ->when($request->search(), fn ($query, $term) => $this->search($query, $term))
            ->when($request->status(), fn ($query, $status) => $query->where('status', $status->value))
            ->when($request->practiceArea(), fn ($query, $id) => $query->whereHas(
                'practiceAreas',
                fn ($areas) => $areas->where('practice_areas.id', $id),
            ))
            ->latest('created_at')
            ->latest('user_id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin.lawyers', [
            'lawyers' => $lawyers,
            'total' => User::lawyers()->count(),
            'practiceAreas' => PracticeArea::orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['q', 'status', 'practice_area']),
        ]);
    }

    public function show(string $id): View
    {
        $lawyer = User::lawyers()
            ->with('lawyerProfile', 'practiceAreas', 'accountActions.admin')
            ->findOrFail($id);

        return view('admin.lawyer-details', ['lawyer' => $lawyer]);
    }

    private function search($query, string $term)
    {
        $like = '%' . addcslashes($term, '%_\\') . '%';

        return $query->where(fn ($q) => $q
            ->where('name', 'like', $like)
            ->orWhere('email', 'like', $like)
            ->orWhere('mobile', 'like', $like));
    }
}
