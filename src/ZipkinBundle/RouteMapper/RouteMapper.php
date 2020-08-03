<?php

namespace ZipkinBundle\RouteMapper;

use Symfony\Component\HttpFoundation\Request;

final class RouteMapper
{
    private const NOT_FOUND = 'not_found';

    /**
     * @var array
     */
    private $routes = [];

    public function __construct(array $routes = [])
    {
        $this->routes = $routes;
    }

    public static function createFromCache(string $cacheDir): self
    {
        $routes = @include CacheWarmer::buildOutputFilename($cacheDir);
        var_dump($routes);
        return new self($routes === false ? [] : $routes);
    }

    public function mapToPath(Request $request): ?string
    {
        if (count($this->routes) === 0) {
            return null;
        }

        $routeName = $request->attributes->get('_route');

        if ($routeName === null || !\array_key_exists($routeName, $this->routes)) {
            return self::NOT_FOUND;
        }

        return $this->routes[$routeName];
    }
}
