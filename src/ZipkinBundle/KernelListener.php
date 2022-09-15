<?php

namespace ZipkinBundle;

use Symfony\Component\HttpKernel\Kernel;
use function Zipkin\Timestamp\now;
use Zipkin\Tracer;
use Zipkin\SpanCustomizerShield;
use Zipkin\Span;
use Zipkin\Propagation\TraceContext;
use Zipkin\Propagation\SamplingFlags;
use Zipkin\Propagation\DefaultSamplingFlags;
use Zipkin\Kind;
use Zipkin\Instrumentation\Http\Server\HttpServerTracing;
use Zipkin\Instrumentation\Http\Server\HttpServerParser;
use ZipkinBundle\RouteMapper\RouteMapper;
use ZipkinBundle\Propagation\RequestHeaders;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpFoundation\Response as HttpFoundationResponse;
use Symfony\Component\HttpFoundation\Request as HttpFoundationRequest;
use Psr\Log\NullLogger;
use Psr\Log\LoggerInterface;
use Exception;

final class KernelListener
{
    private const SPAN_KEY = 'zipkin_bundle_span';
    private const SPAN_CUSTOMIZER_KEY = 'zipkin_bundle_span_customizer';
    private const SCOPE_CLOSER_KEY = 'zipkin_bundle_scope_closer';

    /**
     * @var Tracer
     */
    private $tracer;

    /**
     * @var callable
     */
    private $extractor;

    /**
     * @var HttpServerParser;
     */
    private $parser;

    /**
     * @var callable(Request):?bool
     */
    private $requestSampler;

    /**
     * @var RouteMapper|null
     */
    private $routeMapper;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var array
     */
    private $serverTags;

    public function __construct(
        HttpServerTracing $httpTracing,
        RouteMapper $routeMapper = null,
        LoggerInterface $logger = null,
        array $serverTags = []
    ) {
        $this->tracer = $httpTracing->getTracing()->getTracer();
        $this->extractor = $httpTracing->getTracing()->getPropagation()->getExtractor(new RequestHeaders());
        $this->parser = $httpTracing->getParser();
        $this->requestSampler = $httpTracing->getRequestSampler();
        $this->routeMapper = $routeMapper ?? RouteMapper::createAsNoop();
        $this->logger = $logger ?? new NullLogger;
        $this->serverTags = $serverTags;
    }

    /**
     * @see https://symfony.com/doc/4.4/reference/events.html#kernel-request
     */
    public function onKernelRequest(KernelEvent $event)
    {
        if ((Kernel::MAJOR_VERSION >= 6 && !$event->isMainRequest())
            || (Kernel::MAJOR_VERSION < 6 && !$event->isMasterRequest())) {
            return;
        }

        $request = $event->getRequest();
        $extractedContext = ($this->extractor)($request);
        $span = $this->nextSpan($extractedContext, $request);

        $request->attributes->set(self::SPAN_KEY, $span);
        $scopeCloser = $this->tracer->openScope($span);
        $request->attributes->set(self::SCOPE_CLOSER_KEY, $scopeCloser);

        if ($span->isNoop()) {
            return;
        }

        $span->start();
        $span->setKind(Kind\SERVER);
        $spanCustomizer = new SpanCustomizerShield($span);

        $this->parser->request(new Request($request), $span->getContext(), $spanCustomizer);
        foreach ($this->serverTags as $key => $value) {
            $span->tag($key, $value);
        }

        $request->attributes->set(self::SPAN_CUSTOMIZER_KEY, $spanCustomizer);
    }

    private function nextSpan(?SamplingFlags $extractedContext, HttpFoundationRequest $request): Span
    {
        if ($extractedContext instanceof TraceContext) {
            return $this->tracer->joinSpan($extractedContext);
        }

        $extractedContext = $extractedContext ?? DefaultSamplingFlags::createAsEmpty();
        if ($this->requestSampler === null) {
            return $this->tracer->nextSpan($extractedContext);
        }

        return $this->tracer->nextSpanWithSampler(
            $this->requestSampler,
            [$request],
            $extractedContext
        );
    }

    /**
     * @see https://symfony.com/doc/4.4/reference/events.html#kernel-controller
     */
    public function onKernelController(KernelEvent $event)
    {
        /**
         * @var Span|null
         */
        $span = $event->getRequest()->attributes->get(self::SPAN_KEY);
        if ($span === null || $span->isNoop()) {
            return;
        }

        $span->annotate('symfony.kernel.controller', now());
    }

    /**
     * @see https://symfony.com/doc/4.4/reference/events.html#kernel-exception
     */
    public function onKernelException(ExceptionEvent $event)
    {
        /**
         * @var Span|null
         */
        $span = $event->getRequest()->attributes->get(self::SPAN_KEY);
        if ($span === null || $span->isNoop()) {
            return;
        }

        $span->setError($event->getThrowable());
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

    private function finishSpan(HttpFoundationRequest $request, ?HttpFoundationResponse $response)
    {
        /**
         * @var Span|null
         */
        $span = $request->attributes->get(self::SPAN_KEY);
        if ($span === null) {
            return;
        }

        $scopeCloser = $request->attributes->get(self::SCOPE_CLOSER_KEY);
        if ($scopeCloser !== null) {
            ($scopeCloser)();
            // We reset the scope closer as it did its job
            $request->attributes->remove(self::SCOPE_CLOSER_KEY);
        }

        if ($span->isNoop()) {
            $span->finish();
            return;
        }

        $routeName = $request->attributes->get('_route');
        if ($routeName) {
            $span->tag('symfony.route', $routeName);
        }

        $routePath = $this->routeMapper->mapToPath($request);
        if ($response != null) {
            $this->parser->response(
                new Response($response, new Request($request), $routePath),
                $span->getContext(),
                $request->attributes->get(self::SPAN_CUSTOMIZER_KEY)
            );
        }

        $span->finish();
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
