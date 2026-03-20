<?php

declare(strict_types=1);

use Touta\Aria\Runtime\Http\ResponseInterface;
use Touta\Aria\Runtime\StructuredFailure;
use Touta\Cosan\RouteMatch;
use Touta\Eolas\ArrayLoader;
use Touta\Framework\App;
use Touta\Freagair\JsonResponse;
use Touta\Freagair\Response;
use Touta\Scela\Message;

test('end-to-end: boot app, configure, register routes, handle request, verify response', function (): void {
    // Boot with config
    $app = App::create(new ArrayLoader([
        'app' => ['name' => 'touta-e2e', 'version' => '0.1.0'],
    ]));

    // Verify config flows through
    $nameResult = $app->config()->get('app.name');
    expect($nameResult->isSuccess())->toBeTrue()
        ->and($nameResult->getOrElse(null))->toBe('touta-e2e');

    // Subscribe to lifecycle events
    /** @var list<Message> $events */
    $events = [];
    $app->bus()->subscribe('app.request', function (Message $msg) use (&$events): void {
        $events[] = $msg;
    });
    $app->bus()->subscribe('app.response', function (Message $msg) use (&$events): void {
        $events[] = $msg;
    });

    // Register routes
    $app->routes()->get('/api/greet/{name}', function (RouteMatch $match): ResponseInterface {
        return JsonResponse::from([
            'greeting' => 'Hello, ' . $match->params['name'] . '!',
        ]);
    });

    $app->routes()->post('/api/echo', function (RouteMatch $match): ResponseInterface {
        return new Response(201, ['Content-Type' => ['application/json']], '{"echoed":true}');
    });

    // Handle a GET request
    $getResult = $app->handle('GET', '/api/greet/world');
    expect($getResult->isSuccess())->toBeTrue();

    $getResponse = $getResult->getOrElse(null);
    expect($getResponse)->toBeInstanceOf(ResponseInterface::class)
        ->and($getResponse->statusCode())->toBe(200)
        ->and($getResponse->body())->toContain('Hello, world!');

    // Handle a POST request
    $postResult = $app->handle('POST', '/api/echo');
    expect($postResult->isSuccess())->toBeTrue();

    $postResponse = $postResult->getOrElse(null);
    expect($postResponse)->toBeInstanceOf(ResponseInterface::class)
        ->and($postResponse->statusCode())->toBe(201);

    // Handle a 404
    $notFound = $app->handle('GET', '/missing');
    expect($notFound->isFailure())->toBeTrue()
        ->and($notFound->error())->toBeInstanceOf(StructuredFailure::class);

    // Verify lifecycle events fired for the two successful requests
    $requestEvents = array_filter($events, fn(Message $m) => $m->topic() === 'app.request');
    $responseEvents = array_filter($events, fn(Message $m) => $m->topic() === 'app.response');

    expect(count($requestEvents))->toBe(3)  // GET + POST + 404
        ->and(count($responseEvents))->toBe(2); // only successful ones

    // Verify container has framework services wired
    $container = $app->container();
    expect($container->has(\Touta\Eolas\ConfigRepository::class))->toBeTrue()
        ->and($container->has(\Touta\Cosan\RouteCollection::class))->toBeTrue()
        ->and($container->has(\Touta\Scela\EventBus::class))->toBeTrue();
});
