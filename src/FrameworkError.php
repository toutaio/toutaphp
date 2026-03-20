<?php

declare(strict_types=1);

namespace Touta\Framework;

final readonly class FrameworkError
{
    public const BOOT_FAILED = 'FRAMEWORK.BOOT_FAILED';
    public const ROUTE_FAILED = 'FRAMEWORK.ROUTE_FAILED';
    public const HANDLER_FAILED = 'FRAMEWORK.HANDLER_FAILED';
    public const CONFIG_FAILED = 'FRAMEWORK.CONFIG_FAILED';

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public string $code,
        public string $message,
        /** @var array<string, mixed> */
        public array $context = [],
    ) {}

    public function withMessage(string $message): self
    {
        return new self($this->code, $message, $this->context);
    }

    /** @param array<string, mixed> $context */
    public function withContext(array $context): self
    {
        return new self($this->code, $this->message, array_merge($this->context, $context));
    }
}
