<?php

namespace ZipkinBundle;

use Zipkin\Kind;
use Zipkin\Tags;
use function Zipkin\Timestamp\now;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;

final class Middleware
{
    /**
     * @var Tracer
     */
    private $tracer;
    /**
     * @var bool
     */
    private $isFinished = false;
    /**
     * @var array
     */
    private $tags;
    /**
     * @var SpanCustomizer[]
     */
    private $spanCustomizers;

    /**
     * @var Tracer $tracer
     * @var array $tags
     * @var SpanCustomizer[]|array $spanCustomizers
     */
    public function __construct(
        Tracer $tracer,
        array $tags = [],
        SpanCustomizer ...$spanCustomizers
    ) {
        $this->tracer = $tracer;
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
        $headers = array_map(function ($values) {
            return $values[0];
        }, $request->headers->all());
        $tags = array_merge([
            Tags\HTTP_HOST => $request->getHost(),
            Tags\HTTP_METHOD => $request->getMethod(),
            Tags\HTTP_PATH => $request->getPathInfo(),
        ], $this->tags);

        $this->tracer->prepareSpan($headers, $request->getMethod(), Kind\SERVER);

        foreach ($tags as $tag => $value) {
            $this->tracer->addTag($tag, $value);
        }
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
    public function onKernelException(ExceptionEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        $errorMessage = $event->getThrowable()->getMessage();

        $this->tracer->addTag(Tags\ERROR, $errorMessage);
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


        $this->finishSpan($event->getRequest(), $event->getResponse());


        $this->tracer->flushTracer();
    }

    private function finishSpan(Request $request, $response)
    {
        if (!$this->tracer->spanExist() || $this->isFinished) {
            return;
        }

        $this->tracer->runCustomizers($this->spanCustomizers, $request);

        $routeName = $request->attributes->get('_route');
        if ($routeName) {
            $this->tracer->addTag('symfony.route', $routeName);
        }

        if ($response != null) {
            $statusCode = $response->getStatusCode();
            if ($statusCode > 399) {
                $this->tracer->addTag(Tags\ERROR, (string) $statusCode);
            }
            $this->tracer->addTag(Tags\HTTP_STATUS_CODE, (string) $statusCode);
        }

        $this->tracer->finishSpan();
        $this->isFinished = true;
    }
}
