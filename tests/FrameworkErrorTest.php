<?php

declare(strict_types=1);

use Touta\Framework\FrameworkError;

// Scenario: FrameworkError is constructed with code, message, and optional context
test('FrameworkError holds code, message, and context', function (): void {
    $error = new FrameworkError(
        FrameworkError::BOOT_FAILED,
        'Boot sequence failed',
        ['reason' => 'missing config'],
    );

    expect($error->code)->toBe('FRAMEWORK.BOOT_FAILED')
        ->and($error->message)->toBe('Boot sequence failed')
        ->and($error->context)->toBe(['reason' => 'missing config']);
});

// Scenario: FrameworkError defaults context to empty array
test('FrameworkError defaults context to empty array', function (): void {
    $error = new FrameworkError(FrameworkError::ROUTE_FAILED, 'No route');

    expect($error->context)->toBe([]);
});

// Scenario: FrameworkError::withMessage returns a new instance with updated message
test('withMessage returns new instance with updated message', function (): void {
    $error = new FrameworkError(FrameworkError::HANDLER_FAILED, 'original');
    $updated = $error->withMessage('updated');

    expect($updated->message)->toBe('updated')
        ->and($updated->code)->toBe(FrameworkError::HANDLER_FAILED)
        ->and($error->message)->toBe('original');
});

// Scenario: FrameworkError::withContext merges additional context
test('withContext merges additional context', function (): void {
    $error = new FrameworkError(FrameworkError::CONFIG_FAILED, 'bad config', ['a' => 1]);
    $updated = $error->withContext(['b' => 2]);

    expect($updated->context)->toBe(['a' => 1, 'b' => 2])
        ->and($error->context)->toBe(['a' => 1]);
});

// Scenario: All error code constants are defined
test('all error code constants are defined', function (): void {
    expect(FrameworkError::BOOT_FAILED)->toBe('FRAMEWORK.BOOT_FAILED')
        ->and(FrameworkError::ROUTE_FAILED)->toBe('FRAMEWORK.ROUTE_FAILED')
        ->and(FrameworkError::HANDLER_FAILED)->toBe('FRAMEWORK.HANDLER_FAILED')
        ->and(FrameworkError::CONFIG_FAILED)->toBe('FRAMEWORK.CONFIG_FAILED');
});

// Scenario: FrameworkError is a final readonly class
test('FrameworkError is immutable', function (): void {
    $ref = new ReflectionClass(FrameworkError::class);

    expect($ref->isFinal())->toBeTrue()
        ->and($ref->isReadOnly())->toBeTrue();
});
