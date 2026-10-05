<?php

namespace App\Core\Enums;

enum RoleType: string
{
    case OWNER = 'OWNER';
    case ADMIN = 'ADMIN';
    case EMPLOYEE = 'EMPLOYEE';
    case KITCHEN = 'KITCHEN';
    case SUPPORT = 'SUPPORT';
}
