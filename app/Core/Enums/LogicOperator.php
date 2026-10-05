<?php

namespace App\Core\Enums;

enum LogicOperator: string
{
    case AND = 'AND';
    case OR = 'OR';
    case NOT = 'NOT';
}
