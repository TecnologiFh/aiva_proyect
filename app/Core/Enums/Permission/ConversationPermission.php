<?php

namespace App\Core\Enums\Permission;

enum ConversationPermission: string
{
    case READ_MESSAGES = 'conversation:read';
    case REPLY_MESSAGES = 'conversation:reply';
}
