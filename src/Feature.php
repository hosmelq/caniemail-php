<?php

declare(strict_types=1);

namespace HosmelQ\CanIEmail;

use HosmelQ\CanIEmail\Enums\Category;

final readonly class Feature
{
    public function __construct(
        public Category $category,
        public string $slug,
        public string $title,
        public string $url,
    ) {}
}
