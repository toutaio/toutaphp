<?php

declare(strict_types=1);

namespace Touta\Framework;

final readonly class AppConfig
{
    /** @param array<string, mixed> $value */
    public function __construct(
        /** @var array<string, mixed> */
        public array $value,
    ) {}
}
