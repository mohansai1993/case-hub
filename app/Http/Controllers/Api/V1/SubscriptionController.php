<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\InvalidStateTransition;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Billing\ChangePlanRequest;
use App\Http\Requests\Api\Billing\DowngradePlanRequest;
use App\Models\ClientSubscription;
use App\Models\User;
use App\Services\Billing\StorageQuotaService;
use App\Services\Billing\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Client-only (lawyers have no storage plan - 403 everywhere here). */
class SubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly StorageQuotaService $quota,
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        $client = $request->user('sanctum');

        if (! $client->isClient()) {
            return response()->json(['message' => 'Only clients have a subscription.'], 403);
        }

        $subscription = $client->subscription;

        return response()->json([
            'data' => $subscription ? $this->present($subscription->load('plan'), $client) : null,
        ]);
    }

    public function subscribe(ChangePlanRequest $request): JsonResponse
    {
        return $this->act($request, fn (User $client) => $this->subscriptions->subscribe($client, $request->plan()), 201);
    }

    public function upgrade(ChangePlanRequest $request): JsonResponse
    {
        return $this->act($request, fn (User $client) => $this->subscriptions->upgrade($client, $request->plan()));
    }

    public function downgrade(DowngradePlanRequest $request): JsonResponse
    {
        return $this->act($request, fn (User $client) => $this->subscriptions->downgrade($client, $request->plan()));
    }

    public function cancel(Request $request): JsonResponse
    {
        return $this->act($request, fn (User $client) => $this->subscriptions->cancel($client));
    }

    private function act(Request $request, callable $action, int $status = 200): JsonResponse
    {
        $client = $request->user('sanctum');

        if (! $client->isClient()) {
            return response()->json(['message' => 'Only clients have a subscription.'], 403);
        }

        try {
            $subscription = $action($client);
        } catch (InvalidStateTransition $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Subscription updated.',
            'data' => $this->present($subscription->load('plan'), $client),
        ], $status);
    }

    private function present(ClientSubscription $subscription, User $client): array
    {
        $usedBytes = $this->quota->usedBytes($client);
        $limitBytes = $subscription->plan->storageLimitBytes();

        return [
            'status' => $subscription->status->value,
            'plan' => [
                'id' => $subscription->plan->id,
                'name' => $subscription->plan->name,
                'storage' => $subscription->plan->storageLabel(),
                'price' => $subscription->plan->price,
            ],
            'storage' => [
                'used_bytes' => $usedBytes,
                'limit_bytes' => $limitBytes,
                'is_full' => $usedBytes >= $limitBytes,
            ],
            'current_period_ends_at' => $subscription->current_period_ends_at?->toIso8601String(),
            'cancelled_at' => $subscription->cancelled_at?->toIso8601String(),
            'grace_ends_at' => $subscription->grace_ends_at?->toIso8601String(),
            'restricted_at' => $subscription->restricted_at?->toIso8601String(),
        ];
    }
}
