<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePasswordRequest;
use App\Models\Plan;
use App\Services\Auth\AuthAuditLogger;
use App\Services\Auth\PasswordHistoryGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(
        private readonly AuthAuditLogger $audit,
        private readonly PasswordHistoryGuard $passwords,
    ) {
    }

    public function index(): View
    {
        return view('admin.settings', [
            'plans' => Plan::orderByDesc('is_popular')->orderBy('name')->get(),
        ]);
    }

    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $admin = $request->user('admin');

        $this->passwords->reject($admin, $request->password());
        $this->passwords->remember($admin);

        $admin->forceFill([
            'password' => $request->password(),
            'remember_token' => Str::random(60), // kills "remember me" cookies elsewhere
        ])->save();

        $this->audit->record(AuthAuditLogger::PASSWORD_CHANGED, $admin);

        return response()->json(['message' => 'Password updated successfully.']);
    }

    public function destroy(Request $request): JsonResponse
    {
        $admin = $request->user('admin');

        if ($admin->isSuperAdmin()) {
            return response()->json(['message' => 'The Super Admin account cannot be deleted.'], 422);
        }

        if ($request->input('confirmation') !== 'DELETE') {
            return response()->json(['message' => 'Please type DELETE to confirm.'], 422);
        }

        Auth::guard('admin')->logout();
        $admin->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Your account has been deleted.']);
    }
}
