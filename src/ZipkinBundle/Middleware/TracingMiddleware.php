<?php

namespace ZipkinBundle\Middleware;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Event\PostResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Zipkin\Kind;
use Zipkin\Propagation\DefaultSamplingFlags;
use Zipkin\Propagation\Map;
use Zipkin\Propagation\Propagation;
use Zipkin\Propagation\SamplingFlags;
use Zipkin\Propagation\TraceContext;
use Zipkin\Span;
use Zipkin\Tags;
use Zipkin\Tracer;
use Zipkin\Tracing;
use ZipkinBundle\Controllers\TraceableController;

final class TracingMiddleware
{
    /**
     * @var EventDispatcherInterface
     */
    private $dispatcher;

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
        EventDispatcherInterface $dispatcher,
        Tracing $tracing,
        LoggerInterface $logger
    ) {
        $this->dispatcher = $dispatcher;
        $this->tracing = $tracing;
        $this->logger = $logger;
    }

    public function onKernelController(FilterControllerEvent $event)
    {
        if ($event->getRequestType() === HttpKernelInterface::MASTER_REQUEST) {
            $this->startTracingForMasterRequest($event);
        }
    }

    private function startTracingForMasterRequest(FilterControllerEvent $event)
    {
        $controllerAction = $event->getRequest()->attributes->get('_controller');
        $actionName = substr($controllerAction, strpos($controllerAction, '::') + 2, -6);
        $controller = $event->getController();

        $name = $actionName;
        if ($controller instanceof TraceableController) {
            $name = $controller->operationName($actionName);
        }

        $spanContext = $this->extractContextFromRequest($event->getRequest());

        $span = $this->tracing->getTracer()->nextSpan($spanContext);

        $span->setName($name);
        $span->setKind(Kind\SERVER);
        $span->start();
        $span->tag(Tags\HTTP_METHOD, $event->getRequest()->getMethod());
        $span->tag(Tags\HTTP_PATH, $event->getRequest()->getRequestUri());
        $span->tag('symfony.route', $event->getRequest()->get('_route'));

        $this->scopeCloser = $this->tracing->getTracer()->openScope($span);

        $this->subscribeToFinishEvent();
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

    public function onKernelTerminate(PostResponseEvent $event)
    {
        $span = $this->tracing->getTracer()->getCurrentSpan();

        if ($span !== null) {
            $span->tag(Tags\HTTP_STATUS_CODE, $event->getResponse()->getStatusCode());
            $span->finish();
        }

        $this->flushTracer();
    }

    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        $span = $this->tracing->getTracer()->getCurrentSpan();

        if ($span !== null) {
            $span->tag(Tags\ERROR, $event->getException()->getMessage());
            $span->finish();
            ($this->scopeCloser)();
        }

        $this->flushTracer();
    }

    private function flushTracer()
    {
        register_shutdown_function(function (Tracer $tracer, LoggerInterface $logger) {
            try {
                $tracer->flush();
            } catch (\Exception $e) {
                $logger->error($e->getMessage());
            }
        }, $this->tracing->getTracer(), $this->logger);
    }

    private function subscribeToFinishEvent()
    {
        $middleware = $this;

        $this->dispatcher->addListener('kernel.terminate', function (PostResponseEvent $event) use ($middleware) {
            $middleware->onKernelTerminate($event);
        }, 100);

        $this->dispatcher->addListener('kernel.exception', function (GetResponseForExceptionEvent $event) use ($middleware) {
            $middleware->onKernelException($event);
        }, 100);
    }
}
