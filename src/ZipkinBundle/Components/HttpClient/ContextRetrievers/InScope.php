<?php

namespace ZipkinBundle\Components\HttpClient\ContextRetrievers;

use Zipkin\Tracer;
use Zipkin\Propagation\TraceContext;
use ZipkinBundle\Components\HttpClient\ContextRetriever;

/**
 * InScope retrieves the context from a global state, making it
 * transparent for the user.
 */
final class InScope implements ContextRetriever
{
    /**
     * @var Tracer
     */
    private $tracer;

    public function __construct(Tracer $tracer)
    {
        $this->tracer = $tracer;
    }

    public function __invoke($options): ?TraceContext
    {
        return $this->tracer->getCurrentSpan()->getContext();
    }
}
