<?php

namespace ZipkinBundle\SpanNamers;

use Symfony\Component\HttpFoundation\Request;

interface SpanNamerInterface
{
    /**
     * @param Request $request
     * @return string
     */
    public function getName(Request $request);
}
