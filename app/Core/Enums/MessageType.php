<?php

namespace App\Core\Enums;

enum MessageType: string
{
    case TEXT = 'TEXT';
    case IMAGE = 'IMAGE';
    case AUDIO = 'AUDIO';
    case LOCATION = 'LOCATION';
    case DOCUMENT = 'DOCUMENT';
}
