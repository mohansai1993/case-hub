<?php

namespace App\Enums;

/**
 * A client's subscription lifecycle. "Storage full" is NOT a status here -
 * it's a derived condition (usage >= plan limit) checked at runtime against
 * whichever status the subscription is in, orthogonal to this enum.
 */
enum SubscriptionStatus: string
{
    /** Paying, auto-renewing normally. */
    case Active = 'active';

    /** Client cancelled; still has read access during the 7-day grace window. */
    case Cancelled = 'cancelled';

    /** Grace period expired. Locked out of everything except billing/profile. */
    case Restricted = 'restricted';
}
