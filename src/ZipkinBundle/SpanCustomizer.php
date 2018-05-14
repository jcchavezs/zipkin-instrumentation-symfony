<?php

namespace ZipkinBundle;

use Symfony\Component\HttpFoundation\Request;
use Zipkin\Span;

interface SpanCustomizer
{
    /**
     * @param Span $span
     */
    public function __invoke(Span $span);
}
