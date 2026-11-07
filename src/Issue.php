<?php

declare(strict_types=1);

namespace HosmelQ\CanIEmail;

use HosmelQ\CanIEmail\Enums\Support;

final readonly class Issue
{
    /**
     * @param list<string> $notes
     */
    public function __construct(
        public Client $client,
        public Feature $feature,
        public array $notes,
        public Support $support,
    ) {}
}
