<?php

namespace ZipkinBundle\Components\HttpClient;

use Zipkin\SpanCustomizer;
use Symfony\Contracts\HttpClient\ResponseInterface;

interface HttpParser
{
    /**
     * request parses the incoming data related to a request in order to add
     * relevant information to the span under the SpanCustomizer interface.
     *
     * Basic data being tagged is HTTP method, HTTP path but other information
     * such as query parameters can be added to enrich the span information.
     */
    public function request(
        string $method,
        string $url,
        array $options = [],
        SpanCustomizer $span
    );

    /**
     * response parses the response data in order to add relevant information
     * to the span under the SpanCustomizer interface.
     *
     * Basic data being tagged is HTTP status code but other information such
     * as any response header or redirect_count can be added.
     */
    public function response(ResponseInterface $response, SpanCustomizer $span);
}
