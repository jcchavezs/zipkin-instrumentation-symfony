<?php

namespace ZipkinBundle\SpanNaming;

use Symfony\Component\HttpFoundation\Request;

final class DefaultNaming implements SpanNamingInterface
{
    public static function create()
    {
        return new self();
    }

    /**
     * @inheritdoc
     */
    public function getName(Request $request)
    {
        return $request->getMethod();
    }
}
