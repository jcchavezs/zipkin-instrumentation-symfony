<?php

namespace ZipkinBundle\Components\HttpClient;

use Zipkin\Propagation\TraceContext;

interface ContextRetriever
{
    public function __invoke(array $options): ?TraceContext;
}
