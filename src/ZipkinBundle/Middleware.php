<?php

namespace ZipkinBundle;

use Exception;
use Zipkin\Kind;
use Zipkin\Tags;
use Zipkin\Tracer;
use Zipkin\Tracing;
use Zipkin\Propagation\Map;
use Psr\Log\LoggerInterface;
use function Zipkin\Timestamp\now;
use Zipkin\Propagation\TraceContext;
use Zipkin\Propagation\SamplingFlags;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;

final class Middleware
{
    const SCOPE_CLOSER_KEY = 'zipkin_bundle_scope_closer';

    /**
     * @var Tracer
     */
    private $tracer;

    /**
     * @var callable
     */
    private $extractor;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var array
     */
    private $tags;

    /**
     * @var SpanCustomizer[]|array
     */
    private $spanCustomizers;

    /**
     * @var Tracing $tracing
     * @var LoggerInterface $logger
     * @var array $tags
     * @var SpanCustomizer[]|array $spanCustomizers
     */
    public function __construct(
        Tracing $tracing,
        LoggerInterface $logger,
        array $tags = [],
        SpanCustomizer ...$spanCustomizers
    ) {
        $this->tracer = $tracing->getTracer();
        $this->extractor = $tracing->getPropagation()->getExtractor(new Map());
        $this->logger = $logger;
        $this->tags = $tags;
        $this->spanCustomizers = $spanCustomizers;
    }

    /**
     * @see https://symfony.com/doc/4.4/reference/events.html#kernel-request
     */
    public function onKernelRequest(KernelEvent $event)
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

        $span = $this->tracer->nextSpan($spanContext);
        $span->start();

        if (!$span->isNoop()) {
            $span->setName($request->getMethod());
            $span->setKind(Kind\SERVER);
            $span->tag(Tags\HTTP_HOST, $request->getHost());
            $span->tag(Tags\HTTP_METHOD, $request->getMethod());
            $span->tag(Tags\HTTP_PATH, $request->getPathInfo());
            foreach ($this->tags as $key => $value) {
                $span->tag($key, $value);
            }
        }

        $scopeCloser = $this->tracer->openScope($span);
        $request->attributes->set(self::SCOPE_CLOSER_KEY, $scopeCloser);
    }

    /**
     * @see https://symfony.com/doc/4.4/reference/events.html#kernel-controller
     */
    public function onKernelController(KernelEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        $span = $this->tracer->getCurrentSpan();
        if ($span !== null && !$span->isNoop()) {
            $span->annotate('symfony.kernel.controller', now());
        }
    }

    /**
     * @see https://symfony.com/doc/4.4/reference/events.html#kernel-exception
     */
    public function onKernelException(ExceptionEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        $span = $this->tracer->getCurrentSpan();
        if ($span === null || $span->isNoop()) {
            return;
        }

        $errorMessage = $event->getThrowable()->getMessage();

        $span->tag(Tags\ERROR, $errorMessage);
        $this->finishSpan($event->getRequest(), null);
    }

    /**
     * @see https://symfony.com/doc/4.4/reference/events.html#kernel-response
     */
    public function onKernelResponse(ResponseEvent $event)
    {
        $this->finishSpan($event->getRequest(), $event->getResponse());
    }

    /**
     * @see https://symfony.com/doc/4.4/reference/events.html#kernel-terminate
     */
    public function onKernelTerminate(TerminateEvent $event)
    {
        // Previously, the onKernelResponse listener did not exist in this class
        // and hence we finished the span and closed the scope on the onKernelTerminate.
        // However, terminate occurs after the response has been sent and it could
        // happen that span is being finished after some other processing (potentially
        // expensive) attached by the user. onKernelResponse is the right place to finish
        // the span but in order to not to break existing user relaying on the
        // onKernelTerminate to finish the span we add this temporary fix.

        $scopeCloser = $event->getRequest()->attributes->get(self::SCOPE_CLOSER_KEY);
        if ($scopeCloser !== null) { // i.e. span hasn't been finished
            $this->finishSpan($event->getRequest(), $event->getResponse());
        }

        $this->flushTracer();
    }

    private function finishSpan(Request $request, ?Response $response)
    {
        $span = $this->tracer->getCurrentSpan();
        if ($span === null) {
            return;
        }

        if (!$span->isNoop()) {
            foreach ($this->spanCustomizers as $customizer) {
                $customizer($request, $span);
            }

            $routeName = $request->attributes->get('_route');
            if ($routeName) {
                $span->tag('symfony.route', $routeName);
            }

            if ($response != null) {
                $statusCode = $response->getStatusCode();
                if ($statusCode > 399) {
                    $span->tag(Tags\ERROR, (string) $statusCode);
                }
                $span->tag(Tags\HTTP_STATUS_CODE, (string) $statusCode);
            }
        }

        $span->finish();
        $scopeCloser = $request->attributes->get(self::SCOPE_CLOSER_KEY);
        if ($scopeCloser !== null) {
            ($scopeCloser)();
            // We reset the scope closer as it did its job
            $request->attributes->remove(self::SCOPE_CLOSER_KEY);
        }
    }

    /**
     * @param Request $request
     * @return TraceContext|SamplingFlags|null
     */
    private function extractContextFromRequest(Request $request)
    {
        return ($this->extractor)(array_map(
            function ($values) {
                return $values[0];
            },
            $request->headers->all()
        ));
    }

    private function flushTracer()
    {
        try {
            $this->tracer->flush();
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
        }
    }
}
