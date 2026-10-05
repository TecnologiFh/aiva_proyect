<?php

namespace App\Modules\Multimodal\Enums;

enum VisualRelationType: string
{
    case CONTAINS = 'CONTAINS';
    case BELONGS_TO = 'BELONGS_TO';
    case DESCRIBES = 'DESCRIBES';
    case PRICED_AS = 'PRICED_AS';
    case VARIANT_OF = 'VARIANT_OF';
    case MODIFIER_OF = 'MODIFIER_OF';
    case COMBO_COMPONENT_OF = 'COMBO_COMPONENT_OF';
    case LOCATED_NEAR = 'LOCATED_NEAR';
    case SUPERSEDES = 'SUPERSEDES';
}
