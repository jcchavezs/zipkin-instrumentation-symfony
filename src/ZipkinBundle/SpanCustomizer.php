<?php

namespace ZipkinBundle;

use Zipkin\Span;
use Symfony\Component\HttpFoundation\Request;

/**
 * @deprectated
 */
interface SpanCustomizer
{
    /**
     * @param Request $request
     * @param Span $span
     * @return void
     */
    public function __invoke(Request $request, Span $span);
}
