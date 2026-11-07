<?php

declare(strict_types=1);

namespace HosmelQ\CanIEmail\Exceptions;

use function sprintf;

use InvalidArgumentException;

final class UnknownClient extends InvalidArgumentException
{
    public static function matching(string $pattern): self
    {
        return new self(sprintf('No email client matches "%s".', $pattern));
    }
}
