<?php

declare(strict_types=1);

namespace HosmelQ\CanIEmail\Exceptions;

use UnexpectedValueException;

final class InvalidData extends UnexpectedValueException
{
    public function __construct()
    {
        parent::__construct('The JSON does not match the caniemail data format.');
    }
}
