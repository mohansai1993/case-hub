<?php

namespace App\Enums;

enum CaseAssignmentRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
