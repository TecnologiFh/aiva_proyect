<?php

namespace App\Core\Enums\Permission;

enum OrderPermission: string
{
    case CREATE_ORDER = 'order:create';
    case UPDATE_STATUS = 'order:update_status';
    case CANCEL_ORDER = 'order:cancel';
}
