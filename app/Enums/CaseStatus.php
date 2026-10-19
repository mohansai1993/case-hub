<?php

namespace App\Enums;

enum CaseStatus: string
{
    case Pending = 'pending';
    /** An admin approved a lawyer-assignment request: advocate_id is set, awaiting the lawyer's own accept/reject. */
    case Assigned = 'assigned';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Closed = 'closed';
}
