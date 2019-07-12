<?php

namespace ZipkinBundle;

use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\PostResponseEvent;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Zipkin\Kind;
use Zipkin\Tags;
use Zipkin\Propagation\Map;
use Zipkin\Propagation\TraceContext;
use Zipkin\Propagation\SamplingFlags;
use function Zipkin\Timestamp\now;
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

    /**
     * @var array
     */
    private $tags;

    /**
     * @var SpanCustomizer[]|array
     */
    private $spanCustomizers;

    public function __construct(
        Tracing $tracing,
        LoggerInterface $logger,
        array $tags = [],
        SpanCustomizer ...$spanCustomizers
    ) {
        $this->tracing = $tracing;
        $this->logger = $logger;
        $this->tags = $tags;
        $this->spanCustomizers = $spanCustomizers;
    }

    /**
     * @see https://symfony.com/doc/3.4/reference/events.html#kernel-request
     */
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
        $span->tag(Tags\HTTP_HOST, $request->getHost());
        $span->tag(Tags\HTTP_METHOD, $request->getMethod());
        $span->tag(Tags\HTTP_PATH, $request->getPathInfo());
        $span->tag(Tags\LOCAL_COMPONENT, 'symfony');
        foreach ($this->tags as $key => $value) {
            $span->tag($key, $value);
        }

        $this->scopeCloser = $this->tracing->getTracer()->openScope($span);
    }

    /**
     * @see https://symfony.com/doc/3.4/reference/events.html#kernel-controller
     */
    public function onKernelController(FilterControllerEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        $span = $this->tracing->getTracer()->getCurrentSpan();
        if ($span !== null) {
            $span->annotate('symfony.kernel.controller', now());
        }
    }

    /**
     * @see https://symfony.com/doc/3.4/reference/events.html#kernel-exception
     */
    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        $span = $this->tracing->getTracer()->getCurrentSpan();
        if ($span !== null) {
            $span->tag(Tags\ERROR, $event->getException()->getMessage());
        }
    }

    /**
     * @see https://symfony.com/doc/3.4/reference/events.html#kernel-terminate
     */
    public function onKernelTerminate(PostResponseEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        $request = $event->getRequest();

        $span = $this->tracing->getTracer()->getCurrentSpan();
        if ($span === null) {
            return;
        }

        foreach ($this->spanCustomizers as $customizer) {
            $customizer($request, $span);
        }

        $routeName = $request->attributes->get('_route');
        if ($routeName) {
            $span->tag('symfony.route', $routeName);
        }

        $statusCode = $event->getResponse()->getStatusCode();
        if ($statusCode > 399) {
            $span->tag(Tags\ERROR, (string) $statusCode);
        }

        $span->tag(Tags\HTTP_STATUS_CODE, (string) $statusCode);
        $span->finish();

        call_user_func($this->scopeCloser);

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
