<?php

namespace ZipkinBundle\SpanNamers\Route;

use Symfony\Component\HttpFoundation\Request;
use ZipkinBundle\SpanNamers\SpanNamerInterface;

final class SpanNamer implements SpanNamerInterface
{
    const NOT_FOUND = 'not_found';

    /**
     * @var array
     */
    private $routes;

    private function __construct(array $routes = [])
    {
        $this->routes = $routes;
    }

    public static function create($cacheDir)
    {
        $routes = @include CacheWarmer::buildOutputFilename($cacheDir);
        return new self($routes === false ? [] : $routes);
    }

    /**
     * @inheritdoc
     */
    public function getName(Request $request)
    {
        $method = $request->getMethod();
        $routeName = $request->attributes->get('_route');

        if ($routeName === null) {
            return $method . ' ' . self::NOT_FOUND;
        }

        if (array_key_exists($routeName, $this->routes)) {
            return $method . ' ' . $this->routes[$routeName];
        }

        return $method;
    }
}
