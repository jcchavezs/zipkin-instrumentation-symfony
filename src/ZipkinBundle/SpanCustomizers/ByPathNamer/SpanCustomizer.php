<?php

namespace ZipkinBundle\SpanCustomizers\ByPathNamer;

use Symfony\Component\HttpFoundation\RequestStack;
use Zipkin\Span;
use ZipkinBundle\SpanCustomizer as SpanCustomizerInterface;

final class SpanCustomizer implements SpanCustomizerInterface
{
    const NOT_FOUND = 'not_found';

    /**
     * @var array
     */
    private $routes;

    /**
     * @var RequestStack
     */
    private $requestStack;

    private function __construct(array $routes, RequestStack $requestStack)
    {
        $this->routes = $routes;
        $this->requestStack = $requestStack;
    }

    public static function create($cacheDir, RequestStack $requestStack)
    {
        $routes = @include CacheWarmer::buildOutputFilename($cacheDir);
        return new self($routes === false ? [] : $routes, $requestStack);
    }

    /**
     * @inheritdoc
     */
    public function __invoke(Span $span)
    {
        $request = $this->requestStack->getMasterRequest();
        if ($request === null) {
            return;
        }

        $method = $request->getMethod();
        $routeName = $request->attributes->get('_route');

        if ($routeName === null) {
            $span->setName($method . ' ' . self::NOT_FOUND);
        }

        if (array_key_exists($routeName, $this->routes)) {
            $span->setName($method . ' ' . $this->routes[$routeName]);
        }
    }
}
