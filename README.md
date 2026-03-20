# ToutaPHP

Thin integration framework for the Touta PHP ecosystem. Boots a tiny app using ARIA-based packages.

## Install

```bash
composer require touta/toutaphp
```

## Usage

```php
use Touta\Framework\App;
use Touta\Eolas\ArrayLoader;

$app = App::create(new ArrayLoader(['app' => ['name' => 'MyApp']]));

$app->routes()->get('/hello/{name}', function (string $name): array {
    return ['message' => "Hello, {$name}!"];
});

$response = $app->handle('GET', '/hello/world');
```

## License

MIT
