<?php

namespace ZipkinBundle;

use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Event\PostResponseEvent;
use Zipkin\Kind;
use Zipkin\Propagation\Map;
use Zipkin\Propagation\SamplingFlags;
use Zipkin\Propagation\TraceContext;
use Zipkin\Tags;
use Zipkin\Tracer;
use Zipkin\Tracing;

final class Middleware
{
    /**
     * @var Tracing
     */
    private $tracing;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var callable
     */
    private $scopeCloser;

    public function __construct(
        Tracing $tracing,
        LoggerInterface $logger
    ) {
        $this->tracing = $tracing;
        $this->logger = $logger;
    }

    public function onKernelRequest(GetResponseEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        $request = $event->getRequest();
        try {
            $spanContext = $this->extractContextFromRequest($request);
        } catch (Exception $e) {
            $this->logger->error(
                sprintf('Error when starting the span: %s', $e->getMessage())
            );
            return;
        }

        $span = $this->tracing->getTracer()->nextSpan($spanContext);
        $span->start();
        $span->setName($request->getMethod());
        $span->setKind(Kind\SERVER);
        $span->tag(Tags\HTTP_METHOD, $request->getMethod());
        $span->tag(Tags\HTTP_PATH, $request->getRequestUri());

        $this->scopeCloser = $this->tracing->getTracer()->openScope($span);
    }

    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        $span = $this->tracing->getTracer()->getCurrentSpan();

        if ($span !== null) {
            $span->tag(Tags\ERROR, $event->getException()->getMessage());
        }
    }

    public function onKernelTerminate(PostResponseEvent $event)
    {
        $span = $this->tracing->getTracer()->getCurrentSpan();
        $request = $event->getRequest();

        $routeName = $request->attributes->get('_route');
        if ($routeName) {
            $span->tag('symfony.route', $routeName);
        }

        if ($span !== null) {
            $span->tag(Tags\HTTP_STATUS_CODE, $event->getResponse()->getStatusCode());
            $span->finish();
            ($this->scopeCloser)();
        }

        $this->flushTracer();
    }

    /**
     * @param Request $request
     * @return TraceContext|SamplingFlags|null
     */
    private function extractContextFromRequest(Request $request)
    {
        $extractor = $this->tracing->getPropagation()->getExtractor(new Map());

        return $extractor(array_map(
            function ($values) {
                return $values[0];
            },
            $request->headers->all()
        ));
    }

    private function flushTracer()
    {
        register_shutdown_function(function (Tracer $tracer, LoggerInterface $logger) {
            try {
                $tracer->flush();
            } catch (Exception $e) {
                $logger->error($e->getMessage());
            }
        }, $this->tracing->getTracer(), $this->logger);
    }
}
