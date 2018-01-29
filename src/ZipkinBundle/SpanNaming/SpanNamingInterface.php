<?php

namespace ZipkinBundle\SpanNaming;

use Symfony\Component\HttpFoundation\Request;

interface SpanNamingInterface
{
    /**
     * @param Request $request
     * @return string
     */
    public function getName(Request $request);
}
