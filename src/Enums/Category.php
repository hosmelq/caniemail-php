<?php

declare(strict_types=1);

namespace HosmelQ\CanIEmail\Enums;

enum Category: string
{
    case Css = 'css';
    case Html = 'html';
    case Image = 'image';
    case Others = 'others';
}
