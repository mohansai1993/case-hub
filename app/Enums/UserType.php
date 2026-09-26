<?php

namespace App\Enums;

enum UserType: string
{
    case Client = 'client';
    case Lawyer = 'lawyer';
}
