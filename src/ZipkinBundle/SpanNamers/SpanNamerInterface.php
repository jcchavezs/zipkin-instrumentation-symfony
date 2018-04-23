<?php

namespace ZipkinBundle\SpanNamers;

use Symfony\Component\HttpFoundation\Request;
use Zipkin\Span;

interface SpanNamerInterface
{
    /**
     * @param Request $request
     * @param Span $span
     * @return string
     */
    public function __invoke(Request $request, Span $span);
}
