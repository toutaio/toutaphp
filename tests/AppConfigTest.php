<?php

declare(strict_types=1);

use Touta\Framework\AppConfig;

// Scenario: AppConfig wraps an application configuration array
test('AppConfig wraps a configuration array', function (): void {
    $config = new AppConfig(['app.name' => 'touta', 'debug' => true]);

    expect($config->value)->toBe(['app.name' => 'touta', 'debug' => true]);
});

// Scenario: AppConfig can wrap an empty configuration
test('AppConfig wraps an empty array', function (): void {
    $config = new AppConfig([]);

    expect($config->value)->toBe([]);
});

// Scenario: AppConfig is a final readonly class
test('AppConfig is immutable', function (): void {
    $ref = new ReflectionClass(AppConfig::class);

    expect($ref->isFinal())->toBeTrue()
        ->and($ref->isReadOnly())->toBeTrue();
});
