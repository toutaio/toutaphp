<?php

declare(strict_types=1);

use Touta\Aria\Runtime\Http\ResponseInterface;
use Touta\Cosan\RouteCollection;
use Touta\Cosan\RouteMatch;
use Touta\Eolas\ArrayLoader;
use Touta\Eolas\ConfigRepository;
use Touta\Framework\App;
use Touta\Framework\FrameworkError;
use Touta\Freagair\JsonResponse;
use Touta\Freagair\Response;
use Touta\Nasc\ServiceId;
use Touta\Scela\EventBus;
use Touta\Scela\Message;
use Touta\Scela\TopicName;

// Scenario: Full lifecycle — boot, configure, register, handle, verify events
test('end-to-end: boot app, configure, register routes, handle request, verify response', function (): void {
    // Boot with config
    $app = App::create(new ArrayLoader([
        'app' => ['name' => 'touta-e2e', 'version' => '0.1.0'],
    ]));

    // Verify config flows through
    $nameResult = $app->config()->get(new \Touta\Eolas\ConfigKey('app.name'));
    expect($nameResult->isSuccess())->toBeTrue()
        ->and($nameResult->getOrElse(null))->toBe('touta-e2e');

    // Subscribe to lifecycle events
    /** @var list<Message> $events */
    $events = [];
    $app->bus()->subscribe(new TopicName('app.request'), function (Message $msg) use (&$events): void {
        $events[] = $msg;
    });
    $app->bus()->subscribe(new TopicName('app.response'), function (Message $msg) use (&$events): void {
        $events[] = $msg;
    });

    // Register routes
    $app->routes()->get('/api/greet/{name}', function (RouteMatch $match) {
        return JsonResponse::from([
            'greeting' => 'Hello, ' . $match->params->value['name'] . '!',
        ]);
    });

    $app->routes()->post('/api/echo', function (RouteMatch $match) {
        return new Response(201, ['Content-Type' => ['application/json']], '{"echoed":true}');
    });

    // Handle a GET request
    $getResult = $app->handle('GET', '/api/greet/world');
    expect($getResult->isSuccess())->toBeTrue();

    $getResponse = $getResult->getOrElse(null);
    expect($getResponse)->toBeInstanceOf(ResponseInterface::class)
        ->and($getResponse->statusCode()->value)->toBe(200)
        ->and($getResponse->body()->value)->toContain('Hello, world!');

    // Handle a POST request
    $postResult = $app->handle('POST', '/api/echo');
    expect($postResult->isSuccess())->toBeTrue();

    $postResponse = $postResult->getOrElse(null);
    expect($postResponse)->toBeInstanceOf(ResponseInterface::class)
        ->and($postResponse->statusCode()->value)->toBe(201);

    // Handle a 404
    $notFound = $app->handle('GET', '/missing');
    expect($notFound->isFailure())->toBeTrue()
        ->and($notFound->error())->toBeInstanceOf(FrameworkError::class);

    // Verify lifecycle events fired for the two successful requests
    $requestEvents = array_filter($events, fn(Message $m) => $m->topic()->value === 'app.request');
    $responseEvents = array_filter($events, fn(Message $m) => $m->topic()->value === 'app.response');

    expect(count($requestEvents))->toBe(3)  // GET + POST + 404
        ->and(count($responseEvents))->toBe(2); // only successful ones

    // Verify container has framework services wired
    $container = $app->container();
    expect($container->has(new ServiceId(ConfigRepository::class)))->toBeTrue()
        ->and($container->has(new ServiceId(RouteCollection::class)))->toBeTrue()
        ->and($container->has(new ServiceId(EventBus::class)))->toBeTrue();
});
