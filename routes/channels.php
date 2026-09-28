<?php

use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/**
 * Only the case's own client and advocate may listen on it - this is the
 * only thing standing between "anyone with a token" and someone else's chat.
 */
Broadcast::channel('case.{caseId}', function (User $user, int $caseId) {
    $case = LegalCase::find($caseId);

    return $case !== null && $case->isParticipant($user);
});
