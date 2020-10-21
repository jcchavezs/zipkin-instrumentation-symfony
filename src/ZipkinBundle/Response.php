<?php

namespace ZipkinBundle;

use Zipkin\Instrumentation\Http\Server\Response as ServerResponse;
use Zipkin\Instrumentation\Http\Server\Request as ServerRequest;
use Symfony\Component\HttpFoundation\Response as HttpFoundationResponse;

final class Response extends ServerResponse
{
    /**
     * @var HttpFoundationResponse
     */
    private $delegate;

    /**
     * @var Request|null
     */
    private $request;

    /**
     * @var string|null
     */
    private $route;

    public function __construct(
        HttpFoundationResponse $delegate,
        ?Request $request,
        string $route = null
    ) {
        $this->delegate = $delegate;
        $this->request = $request;
        $this->route = $route;
    }

    public function getRequest(): ?ServerRequest
    {
        return $this->request;
    }

    public function getStatusCode(): int
    {
        return $this->delegate->getStatusCode();
    }

    public function getRoute(): ?string
    {
        return $this->route;
    }

    public function unwrap(): HttpFoundationResponse
    {
        return $this->delegate;
    }
}
