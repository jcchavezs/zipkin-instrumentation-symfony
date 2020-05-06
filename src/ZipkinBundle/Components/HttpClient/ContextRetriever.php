<?php

namespace ZipkinBundle\Components\HttpClient;

use Zipkin\Propagation\TraceContext;

/**
 * ContextRetriever is an API to retrieve the context from the
 * $options array (third parameter in the HttpClient request).
 * Implementations can use different keys from the options or
 * just decide to use the global context in the Scope API.
 */
interface ContextRetriever
{
    public function __invoke(array $options): ?TraceContext;
}
