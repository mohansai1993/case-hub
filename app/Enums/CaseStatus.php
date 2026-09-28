<?php

namespace App\Enums;

enum CaseStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Closed = 'closed';
}
