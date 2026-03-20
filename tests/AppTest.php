<?php

declare(strict_types=1);

use Touta\Aria\Runtime\Http\ResponseInterface;
use Touta\Aria\Runtime\Result;
use Touta\Cosan\RouteCollection;
use Touta\Cosan\RouteMatch;
use Touta\Eolas\ArrayLoader;
use Touta\Eolas\ConfigKey;
use Touta\Eolas\ConfigRepository;
use Touta\Framework\App;
use Touta\Framework\FrameworkError;
use Touta\Freagair\JsonResponse;
use Touta\Nasc\Container;
use Touta\Nasc\ServiceId;
use Touta\Scela\EventBus;
use Touta\Scela\Message;
use Touta\Scela\TopicName;

// --- Creation ---

// Scenario: App boots from a config loader and returns an App instance
test('App::create builds from config loader', function (): void {
    $app = App::create(new ArrayLoader(['app.name' => 'test']));

    expect($app)->toBeInstanceOf(App::class);
});

// Scenario: Config values are accessible via branded ConfigKey
test('config is accessible through the app', function (): void {
    $app = App::create(new ArrayLoader(['app' => ['name' => 'my-app']]));
    $result = $app->config()->get(new ConfigKey('app.name'));

    expect($result->isSuccess())->toBeTrue()
        ->and($result->getOrElse(null))->toBe('my-app');
});

// --- Route registration ---

// Scenario: Routes accessor returns the RouteCollection
test('routes returns a RouteCollection', function (): void {
    $app = App::create(new ArrayLoader([]));

    expect($app->routes())->toBeInstanceOf(RouteCollection::class);
});

// Scenario: Routes can be registered through the collection
test('routes can be registered via the collection', function (): void {
    $app = App::create(new ArrayLoader([]));
    $app->routes()->get('/hello', fn(RouteMatch $m) => JsonResponse::from(['ok' => true]));

    expect($app->routes()->all())->toHaveCount(1);
});

// --- Container ---

// Scenario: Container has default singleton bindings for all framework services
test('container has default bindings for all framework services', function (): void {
    $app = App::create(new ArrayLoader([]));
    $container = $app->container();

    expect($container)->toBeInstanceOf(Container::class)
        ->and($container->has(new ServiceId(ConfigRepository::class)))->toBeTrue()
        ->and($container->has(new ServiceId(RouteCollection::class)))->toBeTrue()
        ->and($container->has(new ServiceId(EventBus::class)))->toBeTrue();
});

// --- Event bus ---

// Scenario: Bus accessor returns the EventBus instance
test('bus returns an EventBus instance', function (): void {
    $app = App::create(new ArrayLoader([]));

    expect($app->bus())->toBeInstanceOf(EventBus::class);
});

// --- Request handling ---

// Scenario: Matching route handler returning ResponseInterface yields Success
test('handle returns Success with ResponseInterface for matching route', function (): void {
    $app = App::create(new ArrayLoader([]));
    $app->routes()->get('/hello/{name}', fn(RouteMatch $m) => JsonResponse::from([
        'hello' => $m->params->value['name'],
    ]));

    $result = $app->handle('GET', '/hello/world');

    expect($result->isSuccess())->toBeTrue();

    $response = $result->getOrElse(null);
    expect($response)->toBeInstanceOf(ResponseInterface::class)
        ->and($response->statusCode()->value)->toBe(200)
        ->and($response->body()->value)->toContain('world');
});

// Scenario: No matching route returns Failure with FrameworkError
test('handle returns Failure when no route matches', function (): void {
    $app = App::create(new ArrayLoader([]));

    $result = $app->handle('GET', '/nothing');

    expect($result->isFailure())->toBeTrue()
        ->and($result->error())->toBeInstanceOf(FrameworkError::class)
        ->and($result->error()->code)->toBe(FrameworkError::ROUTE_FAILED);
});

// Scenario: Handler returning Result is passed through directly
test('handler returning Result is passed through directly', function (): void {
    $app = App::create(new ArrayLoader([]));
    $app->routes()->get('/direct', fn(RouteMatch $m) => JsonResponse::from(['direct' => true]));

    $result = $app->handle('GET', '/direct');

    expect($result->isSuccess())->toBeTrue();

    $response = $result->getOrElse(null);
    expect($response)->toBeInstanceOf(ResponseInterface::class);
});

// Scenario: Handler throwing exception returns Failure with FrameworkError
test('handler throwing exception returns Failure with FrameworkError', function (): void {
    $app = App::create(new ArrayLoader([]));
    $app->routes()->get('/boom', function (RouteMatch $m): never {
        throw new RuntimeException('Boom!');
    });

    $result = $app->handle('GET', '/boom');

    expect($result->isFailure())->toBeTrue()
        ->and($result->error())->toBeInstanceOf(FrameworkError::class)
        ->and($result->error()->code)->toBe(FrameworkError::HANDLER_FAILED)
        ->and($result->error()->message)->toBe('Boom!');
});

// Scenario: Handler returning non-Response type returns Failure with FrameworkError
test('handler returning non-Response type returns Failure', function (): void {
    $app = App::create(new ArrayLoader([]));
    $app->routes()->get('/bad', fn(RouteMatch $m) => 'not a response');

    $result = $app->handle('GET', '/bad');

    expect($result->isFailure())->toBeTrue()
        ->and($result->error())->toBeInstanceOf(FrameworkError::class)
        ->and($result->error()->code)->toBe(FrameworkError::HANDLER_FAILED);
});

// --- Event integration ---

// Scenario: app.request event is published before routing occurs
test('handle publishes app.request event before routing', function (): void {
    $app = App::create(new ArrayLoader([]));

    /** @var list<Message> $received */
    $received = [];
    $app->bus()->subscribe(new TopicName('app.request'), function (Message $msg) use (&$received): void {
        $received[] = $msg;
    });

    $app->routes()->get('/evented', fn(RouteMatch $m) => JsonResponse::from(['ok' => true]));
    $app->handle('GET', '/evented');

    expect($received)->toHaveCount(1)
        ->and($received[0]->topic()->value)->toBe('app.request')
        ->and($received[0]->payload()->value)->toBe(['method' => 'GET', 'uri' => '/evented']);
});

// Scenario: app.response event is published on successful handling
test('handle publishes app.response event on success', function (): void {
    $app = App::create(new ArrayLoader([]));

    /** @var list<Message> $received */
    $received = [];
    $app->bus()->subscribe(new TopicName('app.response'), function (Message $msg) use (&$received): void {
        $received[] = $msg;
    });

    $app->routes()->get('/ok', fn(RouteMatch $m) => JsonResponse::from(['ok' => true]));
    $app->handle('GET', '/ok');

    expect($received)->toHaveCount(1)
        ->and($received[0]->topic()->value)->toBe('app.response')
        ->and($received[0]->payload()->value['status']->value)->toBe(200);
});
