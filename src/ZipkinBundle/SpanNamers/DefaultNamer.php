<?php

namespace ZipkinBundle\SpanNamers;

use Symfony\Component\HttpFoundation\Request;

final class DefaultNamer implements SpanNamerInterface
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
