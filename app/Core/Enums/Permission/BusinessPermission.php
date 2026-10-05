<?php

namespace App\Core\Enums\Permission;

enum BusinessPermission: string
{
    case MANAGE_SETTINGS = 'tenant:manage_settings';
    case MANAGE_PRODUCTS = 'business:manage_products';
    case VIEW_PRODUCTS = 'business:view_products';
}
