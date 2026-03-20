<?php

declare(strict_types=1);

namespace Touta\Framework;

use Touta\Aria\Runtime\Failure;
use Touta\Aria\Runtime\Http\ResponseInterface;
use Touta\Aria\Runtime\Result;
use Touta\Aria\Runtime\StructuredFailure;
use Touta\Aria\Runtime\Success;
use Touta\Cosan\RouteCollection;
use Touta\Cosan\Router;
use Touta\Eolas\ConfigLoaderInterface;
use Touta\Eolas\ConfigRepository;
use Touta\Nasc\Container;
use Touta\Scela\EventBus;
use Touta\Scela\Message;

/**
 * Thin integration kernel for the Touta PHP framework.
 *
 * Wires config, container, router, response, and event bus
 * without absorbing their responsibilities.
 */
final class App
{
    private readonly ConfigRepository $config;
    private readonly RouteCollection $routeCollection;
    private readonly EventBus $eventBus;
    private readonly Container $container;

    private function __construct(
        ConfigRepository $config,
        RouteCollection $routeCollection,
        EventBus $eventBus,
        Container $container,
    ) {
        $this->config = $config;
        $this->routeCollection = $routeCollection;
        $this->eventBus = $eventBus;
        $this->container = $container;
    }

    public static function create(ConfigLoaderInterface $loader): self
    {
        $config = ConfigRepository::fromLoader($loader);
        $routes = new RouteCollection();
        $bus = new EventBus();

        $container = Container::create()
            ->singleton(ConfigRepository::class, static fn(): ConfigRepository => $config)
            ->singleton(RouteCollection::class, static fn(): RouteCollection => $routes)
            ->singleton(EventBus::class, static fn(): EventBus => $bus);

        return new self($config, $routes, $bus, $container);
    }

    public function config(): ConfigRepository
    {
        return $this->config;
    }

    public function routes(): RouteCollection
    {
        return $this->routeCollection;
    }

    public function bus(): EventBus
    {
        return $this->eventBus;
    }

    public function container(): Container
    {
        return $this->container;
    }

    /**
     * Route a request and run its handler.
     *
     * On success the Result wraps a ResponseInterface.
     * On failure the Result wraps a StructuredFailure.
     *
     * @return Result<ResponseInterface, StructuredFailure>
     */
    public function handle(string $method, string $uri): Result
    {
        $this->eventBus->publish(new Message('app.request', [
            'method' => $method,
            'uri' => $uri,
        ]));

        $router = new Router($this->routeCollection);
        $matchResult = $router->match($method, $uri);

        if ($matchResult->isFailure()) {
            /** @var Failure<StructuredFailure> $matchResult */
            return $matchResult;
        }

        /** @var \Touta\Cosan\RouteMatch $match */
        $match = $matchResult->getOrElse(null);
        $handler = $match->route->handler;

        try {
            /** @var mixed $response */
            $response = $handler($match);
        } catch (\Throwable $e) {
            return Failure::from(new StructuredFailure(
                'handler.error',
                $e->getMessage(),
                ['exception' => $e::class],
            ));
        }

        if ($response instanceof Result) {
            /** @var Result<ResponseInterface, StructuredFailure> $response */
            return $response;
        }

        if ($response instanceof ResponseInterface) {
            $this->eventBus->publish(new Message('app.response', [
                'status' => $response->statusCode(),
            ]));

            return Success::of($response); // @phpstan-ignore return.type (Result<T,E> not covariant)
        }

        return Failure::from(new StructuredFailure(
            'handler.invalid_return',
            'Handler must return ResponseInterface or Result',
        ));
    }
}
