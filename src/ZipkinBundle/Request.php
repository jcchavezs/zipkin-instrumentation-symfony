<?php

namespace ZipkinBundle;

use Zipkin\Instrumentation\Http\Server\Request as ServerRequest;
use Symfony\Component\HttpFoundation\Request as HttpFoundationRequest;

final class Request extends ServerRequest
{
    /**
     * @var HttpFoundationRequest
     */
    private $delegate;

    /**
     * @var string|null
     */
    private $route;

    public function __construct(HttpFoundationRequest $delegate, ?string $route = null)
    {
        $this->delegate = $delegate;
        $this->route = $route;
    }

    public function getMethod(): string
    {
        return $this->delegate->getMethod();
    }

    public function getPath(): ?string
    {
        return $this->delegate->getPathInfo() ?: '/';
    }

    public function getUrl(): string
    {
        return $this->delegate->getUri();
    }

    public function getHeader(string $name): ?string
    {
        return $this->delegate->headers->get($name);
    }

    /**
     * @return HttpFoundationRequest
     */
    public function unwrap()
    {
        return $this->delegate;
    }

    public function getRoute(): ?string
    {
        return $this->route;
    }
}
