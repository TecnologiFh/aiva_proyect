<?php

namespace App\Core\Enums;

enum ChannelType: string
{
    case WHATSAPP = 'WHATSAPP';
    case INSTAGRAM = 'INSTAGRAM';
    case MESSENGER = 'MESSENGER';
    case TELEGRAM = 'TELEGRAM';
    case WEB = 'WEB';
}
