<?php

namespace ZipkinBundle;

use function Zipkin\Timestamp\now;
use Zipkin\Tracing;
use Zipkin\Tracer;
use Zipkin\Tags;
use Zipkin\Kind;
use ZipkinBundle\Propagation\RequestHeaders;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\HttpFoundation\Request;
use Psr\Log\LoggerInterface;
use Exception;

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
     * @var bool
     */
    private $usesDeprecatedEvents;

    public function __construct(
        Tracing $tracing,
        LoggerInterface $logger,
        array $tags = [],
        SpanCustomizer ...$spanCustomizers
    ) {
        $this->tracer = $tracing->getTracer();
        $this->extractor = $tracing->getPropagation()->getExtractor(new RequestHeaders());
        $this->logger = $logger;
        $this->tags = $tags;
        $this->spanCustomizers = $spanCustomizers;
        $this->usesDeprecatedEvents = (Kernel::VERSION[0] === '3');
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
        $spanContext = ($this->extractor)($request);

        $span = $this->tracer->nextSpan($spanContext);
        $span->start();
        $span->setName($request->getMethod());
        $span->setKind(Kind\SERVER);
        $span->tag(Tags\HTTP_HOST, $request->getHost());
        $span->tag(Tags\HTTP_METHOD, $request->getMethod());
        $span->tag(Tags\HTTP_PATH, $request->getPathInfo());
        foreach ($this->tags as $key => $value) {
            $span->tag($key, $value);
        }

        $request->attributes->set(self::SCOPE_CLOSER_KEY, $this->tracer->openScope($span));
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
        if ($span !== null) {
            $span->annotate('symfony.kernel.controller', now());
        }
    }

    /**
     * @see https://symfony.com/doc/4.4/reference/events.html#kernel-exception
     */
    public function onKernelException(KernelEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        $span = $this->tracer->getCurrentSpan();
        if ($span === null) {
            return;
        }

        if ($this->usesDeprecatedEvents) {
            /**
             * @var \Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent $event
             */
            $e = $event->getException();
        } else {
            /**
             * @var \Symfony\Component\HttpKernel\Event\ExceptionEvent $event
             */
            $e = $event->getThrowable();
        }

        $span->setError($e);
        $this->finishSpan($event->getRequest(), null);
    }

    /**
     * @see https://symfony.com/doc/4.4/reference/events.html#kernel-response
     */
    public function onKernelResponse(KernelEvent $event)
    {
        if ($this->usesDeprecatedEvents) {
            /**
             * @var \Symfony\Component\HttpKernel\Event\FilterResponseEvent $event
             */
        } else {
            /**
             * @var \Symfony\Component\HttpKernel\Event\ResponseEvent $event
             */
        }

        $this->finishSpan($event->getRequest(), $event->getResponse());
    }

    /**
     * @see https://symfony.com/doc/4.4/reference/events.html#kernel-terminate
     */
    public function onKernelTerminate(KernelEvent $event)
    {
        // Previously, the onKernelResponse listener did not exist in this class
        // and hence we finished the span and closed the scope on the onKernelTerminate.
        // However, terminate occurs after the response has been sent and it could
        // happen that span is being finished after some other processing (potentially
        // expensive) attached by the user. onKernelResponse is the right place to finish
        // the span but in order to not to break existing user relaying on the
        // onKernelTerminate to finish the span we add this temporary fix.

        $scopeCloser = $event->getRequest()->attributes->get(self::SCOPE_CLOSER_KEY);
        if ($scopeCloser !== null) {
            if ($this->usesDeprecatedEvents) {
                /**
                 * @var \Symfony\Component\HttpKernel\Event\PostResponseEvent $event
                 */
            } else {
                /**
                 * @var \Symfony\Component\HttpKernel\Event\TerminateEvent $event
                 */
            }

            $this->finishSpan($event->getRequest(), $event->getResponse());
        }

        $this->flushTracer();
    }

    private function finishSpan(Request $request, $response)
    {
        $span = $this->tracer->getCurrentSpan();
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

        if ($response != null) {
            $statusCode = $response->getStatusCode();
            if ($statusCode > 399) {
                $span->tag(Tags\ERROR, (string) $statusCode);
            }
            $span->tag(Tags\HTTP_STATUS_CODE, (string) $statusCode);
        }

        $span->finish();
        $scopeCloser = $request->attributes->get(self::SCOPE_CLOSER_KEY);
        if ($scopeCloser !== null) {
            $scopeCloser();
            // We reset the scope closer as it did its job
            $request->attributes->remove(self::SCOPE_CLOSER_KEY);
        }
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
