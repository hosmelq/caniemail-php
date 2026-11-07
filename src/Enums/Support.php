<?php

declare(strict_types=1);

namespace HosmelQ\CanIEmail\Enums;

enum Support: string
{
    case Full = 'y';
    case None = 'n';
    case Partial = 'a';
    case Unknown = 'u';
}
