<?php

use App\Core\Enums\RoleType;
use App\Core\Enums\Permission\IdentityPermission;
use App\Core\Enums\Permission\BusinessPermission;
use App\Core\Enums\Permission\OrderPermission;
use App\Core\Enums\Permission\ConversationPermission;
use App\Core\Enums\Permission\ReportPermission;

return [
    /*
    |--------------------------------------------------------------------------
    | Matriz de Roles y Permisos de AIVA (RBAC Source of Truth)
    |--------------------------------------------------------------------------
    |
    | Asocia cada rol de la plataforma con sus permisos iniciales.
    | IdentityInitializationSeeder lee esta matriz para sincronizar la base de datos.
    |
    */
    'matrix' => [
        RoleType::OWNER->value => [
            IdentityPermission::MANAGE_USERS->value,
            IdentityPermission::ASSIGN_ROLES->value,
            IdentityPermission::VIEW_USERS->value,
            BusinessPermission::MANAGE_SETTINGS->value,
            BusinessPermission::MANAGE_PRODUCTS->value,
            BusinessPermission::VIEW_PRODUCTS->value,
            OrderPermission::CREATE_ORDER->value,
            OrderPermission::UPDATE_STATUS->value,
            OrderPermission::CANCEL_ORDER->value,
            ConversationPermission::READ_MESSAGES->value,
            ConversationPermission::REPLY_MESSAGES->value,
            ReportPermission::VIEW_REPORTS->value,
        ],

        RoleType::ADMIN->value => [
            IdentityPermission::MANAGE_USERS->value,
            IdentityPermission::VIEW_USERS->value,
            BusinessPermission::MANAGE_SETTINGS->value,
            BusinessPermission::MANAGE_PRODUCTS->value,
            BusinessPermission::VIEW_PRODUCTS->value,
            OrderPermission::CREATE_ORDER->value,
            OrderPermission::UPDATE_STATUS->value,
            OrderPermission::CANCEL_ORDER->value,
            ConversationPermission::READ_MESSAGES->value,
            ConversationPermission::REPLY_MESSAGES->value,
            ReportPermission::VIEW_REPORTS->value,
        ],

        RoleType::EMPLOYEE->value => [
            BusinessPermission::VIEW_PRODUCTS->value,
            OrderPermission::CREATE_ORDER->value,
            OrderPermission::UPDATE_STATUS->value,
            ConversationPermission::READ_MESSAGES->value,
            ConversationPermission::REPLY_MESSAGES->value,
        ],

        RoleType::KITCHEN->value => [
            OrderPermission::UPDATE_STATUS->value,
        ],

        RoleType::SUPPORT->value => [
            IdentityPermission::VIEW_USERS->value,
            BusinessPermission::VIEW_PRODUCTS->value,
            ConversationPermission::READ_MESSAGES->value,
            ReportPermission::VIEW_REPORTS->value,
        ],
    ],
];
