<?php

declare(strict_types=1);

namespace HosmelQ\CanIEmail;

final readonly class Client
{
    public string $id;

    public function __construct(
        public string $family,
        public string $familyName,
        public string $platform,
        public string $platformName,
    ) {
        $this->id = "{$family}.{$platform}";
    }
}
