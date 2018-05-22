<?php

namespace ZipkinBundle;

use Symfony\Component\HttpFoundation\Request;
use Zipkin\Span;

interface SpanCustomizer
{
    /**
     * @param Request $request
     * @param Span $span
     * @return void
     */
    public function __invoke(Request $request, Span $span);
}
