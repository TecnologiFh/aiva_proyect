<?php

namespace App\Core\Enums\Permission;

enum IdentityPermission: string
{
    case MANAGE_USERS = 'identity:manage_users';
    case ASSIGN_ROLES = 'identity:assign_roles';
    case VIEW_USERS = 'identity:view_users';
}
