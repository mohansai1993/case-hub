<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\InvalidStateTransition;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReasonRequest;
use App\Models\User;
use App\Services\Admin\AccountModerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * JSON endpoints behind the SweetAlert buttons on the client / lawyer pages.
 *
 * The route decides which kind of account is being touched (`type` default)
 * and which permission is needed, so an admin allowed to manage clients can
 * never reach a lawyer through a client URL, or vice versa.
 */
class AccountActionController extends Controller
{
    public function __construct(private readonly AccountModerationService $moderation)
    {
    }

    public function suspend(ReasonRequest $request, string $id): JsonResponse
    {
        $user = $this->resolve($request, $id);

        return $this->run(fn () => $this->moderation->suspend($user, $request->user('admin'), $request->reason()), 'Account suspended.');
    }

    public function activate(Request $request, string $id): JsonResponse
    {
        $user = $this->resolve($request, $id);

        return $this->run(fn () => $this->moderation->activate($user, $request->user('admin')), 'Account activated.');
    }

    private function resolve(Request $request, string $id): User
    {
        return User::where('type', $request->route('type'))->findOrFail($id);
    }

    private function run(callable $action, string $successMessage): JsonResponse
    {
        try {
            $user = $action();
        } catch (InvalidStateTransition $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => $successMessage,
            'data' => ['status' => $user->status->value],
        ]);
    }
}
