<?php

namespace ZipkinBundle;

use Zipkin\Instrumentation\Http\Server\Response as ServerResponse;
use Zipkin\Instrumentation\Http\Server\Request as ServerRequest;
use Symfony\Component\HttpFoundation\Response as HttpFoundationResponse;
use Symfony\Component\HttpFoundation\Request as HttpFoundationRequest;

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

    public function __construct(
        HttpFoundationResponse $delegate,
        ?Request $request
    ) {
        $this->delegate = $delegate;
        $this->request = $request;
    }

    public function getRequest(): ?ServerRequest
    {
        return $this->request;
    }

    public function getStatusCode(): int
    {
        return $this->delegate->getStatusCode();
    }

    /**
     * @return HttpFoundationRequest
     */
    public function unwrap()
    {
        return $this->delegate;
    }
}
