<?php

namespace App\Enums;

/** Admin review state of a lawyer's profile. */
enum VerificationStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';
}
