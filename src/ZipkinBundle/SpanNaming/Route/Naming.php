<?php

namespace ZipkinBundle\SpanNaming\Route;

use Symfony\Component\HttpFoundation\Request;
use ZipkinBundle\SpanNaming\SpanNamingInterface;

final class Naming implements SpanNamingInterface
{
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
        $routeName = $request->request->get('_route');

        if (array_key_exists($routeName, $this->routes)) {
            return $request->getMethod() . ' ' . $this->routes[$routeName];
        }

        return $request->getMethod();
    }
}
