<?php

namespace ZipkinBundle\Components\HttpClient\ContextRetrievers;

use Zipkin\Propagation\TraceContext;
use ZipkinBundle\Components\HttpClient\ContextRetriever;

/**
 * InRequest stores and retrieves the tracing context from in the
 * request options, making it explicit but embracing event loop
 * models.
 */
final class InRequest implements ContextRetriever
{
    const TRACE_CONTEXT_EXTRA_KEY = 'zipkin_bundle_trace_context';

    public static function wrapOptions(TraceContext $context, array $options = []): array
    {
        return $options + ['extra' => $options['extra'] + [self::TRACE_CONTEXT_EXTRA_KEY => $context]];
    }

    public function __invoke(array $options): ?TraceContext
    {
        if (array_key_exists('extra', $options) && array_key_exists(self::TRACE_CONTEXT_EXTRA_KEY, $options['extra'])) {
            return $options['extra'][self::TRACE_CONTEXT_EXTRA_KEY];
        }

        return null;
    }
}
