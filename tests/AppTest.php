<?php

declare(strict_types=1);

use Touta\Aria\Runtime\Http\ResponseInterface;
use Touta\Aria\Runtime\Result;
use Touta\Aria\Runtime\StructuredFailure;
use Touta\Cosan\RouteCollection;
use Touta\Cosan\RouteMatch;
use Touta\Eolas\ArrayLoader;
use Touta\Eolas\ConfigRepository;
use Touta\Framework\App;
use Touta\Freagair\JsonResponse;
use Touta\Nasc\Container;
use Touta\Scela\EventBus;
use Touta\Scela\Message;

// --- Creation ---

test('App::create builds from config loader', function (): void {
    $app = App::create(new ArrayLoader(['app.name' => 'test']));

    expect($app)->toBeInstanceOf(App::class);
});

test('config is accessible through the app', function (): void {
    $app = App::create(new ArrayLoader(['app' => ['name' => 'my-app']]));
    $result = $app->config()->get('app.name');

    expect($result->isSuccess())->toBeTrue()
        ->and($result->getOrElse(null))->toBe('my-app');
});

// --- Route registration ---

test('routes returns a RouteCollection', function (): void {
    $app = App::create(new ArrayLoader([]));

    expect($app->routes())->toBeInstanceOf(RouteCollection::class);
});

test('routes can be registered via the collection', function (): void {
    $app = App::create(new ArrayLoader([]));
    $app->routes()->get('/hello', fn(RouteMatch $m) => JsonResponse::from(['ok' => true]));

    expect($app->routes()->all())->toHaveCount(1);
});

// --- Container ---

test('container has default bindings for all framework services', function (): void {
    $app = App::create(new ArrayLoader([]));
    $container = $app->container();

    expect($container)->toBeInstanceOf(Container::class)
        ->and($container->has(ConfigRepository::class))->toBeTrue()
        ->and($container->has(RouteCollection::class))->toBeTrue()
        ->and($container->has(EventBus::class))->toBeTrue();
});

// --- Event bus ---

test('bus returns an EventBus instance', function (): void {
    $app = App::create(new ArrayLoader([]));

    expect($app->bus())->toBeInstanceOf(EventBus::class);
});

// --- Request handling ---

test('handle returns Success with ResponseInterface for matching route', function (): void {
    $app = App::create(new ArrayLoader([]));
    $app->routes()->get('/hello/{name}', fn(RouteMatch $m) => JsonResponse::from([
        'hello' => $m->params['name'],
    ]));

    $result = $app->handle('GET', '/hello/world');

    expect($result->isSuccess())->toBeTrue();

    $response = $result->getOrElse(null);
    expect($response)->toBeInstanceOf(ResponseInterface::class)
        ->and($response->statusCode())->toBe(200)
        ->and($response->body())->toContain('world');
});

test('handle returns Failure when no route matches', function (): void {
    $app = App::create(new ArrayLoader([]));

    $result = $app->handle('GET', '/nothing');

    expect($result->isFailure())->toBeTrue();
});

test('handler returning Result is passed through directly', function (): void {
    $app = App::create(new ArrayLoader([]));
    $app->routes()->get('/direct', fn(RouteMatch $m) => Result::success(
        JsonResponse::from(['direct' => true]),
    ));

    $result = $app->handle('GET', '/direct');

    expect($result->isSuccess())->toBeTrue();

    $response = $result->getOrElse(null);
    expect($response)->toBeInstanceOf(ResponseInterface::class);
});

test('handler throwing exception returns Failure with structured error', function (): void {
    $app = App::create(new ArrayLoader([]));
    $app->routes()->get('/boom', function (RouteMatch $m): never {
        throw new RuntimeException('Boom!');
    });

    $result = $app->handle('GET', '/boom');

    expect($result->isFailure())->toBeTrue()
        ->and($result->error())->toBeInstanceOf(StructuredFailure::class)
        ->and($result->error()->code())->toBe('handler.error')
        ->and($result->error()->message())->toBe('Boom!');
});

test('handler returning non-Response type returns Failure', function (): void {
    $app = App::create(new ArrayLoader([]));
    $app->routes()->get('/bad', fn(RouteMatch $m) => 'not a response');

    $result = $app->handle('GET', '/bad');

    expect($result->isFailure())->toBeTrue()
        ->and($result->error())->toBeInstanceOf(StructuredFailure::class)
        ->and($result->error()->code())->toBe('handler.invalid_return');
});

// --- Event integration ---

test('handle publishes app.request event before routing', function (): void {
    $app = App::create(new ArrayLoader([]));

    /** @var list<Message> $received */
    $received = [];
    $app->bus()->subscribe('app.request', function (Message $msg) use (&$received): void {
        $received[] = $msg;
    });

    $app->routes()->get('/evented', fn(RouteMatch $m) => JsonResponse::from(['ok' => true]));
    $app->handle('GET', '/evented');

    expect($received)->toHaveCount(1)
        ->and($received[0]->topic())->toBe('app.request')
        ->and($received[0]->payload())->toBe(['method' => 'GET', 'uri' => '/evented']);
});

test('handle publishes app.response event on success', function (): void {
    $app = App::create(new ArrayLoader([]));

    /** @var list<Message> $received */
    $received = [];
    $app->bus()->subscribe('app.response', function (Message $msg) use (&$received): void {
        $received[] = $msg;
    });

    $app->routes()->get('/ok', fn(RouteMatch $m) => JsonResponse::from(['ok' => true]));
    $app->handle('GET', '/ok');

    expect($received)->toHaveCount(1)
        ->and($received[0]->topic())->toBe('app.response')
        ->and($received[0]->payload()['status'])->toBe(200);
});
