<?php

namespace App\Services\Billing;

use App\Models\CaseDocument;
use App\Models\User;

/** How much of a client's document vault is used versus their plan's limit. */
class StorageQuotaService
{
    public function usedBytes(User $client): int
    {
        return (int) CaseDocument::accessible()
            ->whereHas('case', fn ($query) => $query->where('client_id', $client->user_id))
            ->sum('size_bytes');
    }

    public function limitBytes(User $client): int
    {
        return $client->subscription?->plan->storageLimitBytes() ?? 0;
    }

    public function remainingBytes(User $client): int
    {
        return max(0, $this->limitBytes($client) - $this->usedBytes($client));
    }

    public function isFull(User $client): bool
    {
        return $this->usedBytes($client) >= $this->limitBytes($client);
    }

    /** Can this many more bytes be uploaded right now (subscription state + quota)? */
    public function canUpload(User $client, int $incomingBytes): bool
    {
        if (! $this->canCreateNewContent($client)) {
            return false;
        }

        return ($this->usedBytes($client) + $incomingBytes) <= $this->limitBytes($client);
    }

    /**
     * Can this client add anything new at all - upload a document, submit a
     * case? True only while paying (active or still in grace) AND storage
     * is not already full. False once restricted, or with no plan at all.
     */
    public function canCreateNewContent(User $client): bool
    {
        $subscription = $client->subscription;

        if (! $subscription || ! $subscription->hasReadAccess()) {
            return false;
        }

        return ! $this->isFull($client);
    }
}
