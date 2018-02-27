<?php

namespace ZipkinBundle\Tests\Unit;

use Exception;
use PHPUnit_Framework_TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Event\PostResponseEvent;
use Zipkin\Span;
use Zipkin\TracingBuilder;
use ZipkinBundle\Middleware;

class MiddlewareTest extends PHPUnit_Framework_TestCase
{
    public function testSpanIsNotCreatedOnNonMasterRequest()
    {
        $tracing = TracingBuilder::create()->build();
        $logger = new NullLogger();

        $middleware = new Middleware($tracing, $logger);

        $event = $this->prophesize(GetResponseEvent::class);
        $event->isMasterRequest()->willReturn(false);

        $middleware->onKernelRequest($event->reveal());

        $this->assertNull($tracing->getTracer()->getCurrentSpan());
    }

    public function testSpanIsCreatedOnKernelRequest()
    {
        $tracing = TracingBuilder::create()->build();
        $logger = new NullLogger();

        $middleware = new Middleware($tracing, $logger);

        $event = $this->prophesize(GetResponseEvent::class);
        $event->isMasterRequest()->willReturn(true);
        $event->getRequest()->willReturn(new Request());

        $middleware->onKernelRequest($event->reveal());

        $this->assertInstanceOf(Span::class, $tracing->getTracer()->getCurrentSpan());
    }

    public function testNoSpanIsTaggedOnKernelExceptionIfItIsNotStarted()
    {
        $tracing = TracingBuilder::create()->build();
        $logger = new NullLogger();

        $middleware = new Middleware($tracing, $logger);

        $event = $this->prophesize(GetResponseEvent::class);
        $event->isMasterRequest()->willReturn(false);
        $event->getRequest()->willReturn(new Request());

        $middleware->onKernelRequest($event->reveal());

        $exceptionEvent = $this->prophesize(GetResponseForExceptionEvent::class);
        $exceptionEvent->getException()->shouldNotBeCalled();
        $middleware->onKernelException($exceptionEvent->reveal());
    }

    public function testSpanIsTaggedOnKernelException()
    {
        $tracing = TracingBuilder::create()->build();
        $logger = new NullLogger();

        $middleware = new Middleware($tracing, $logger);

        $event = $this->prophesize(GetResponseEvent::class);
        $event->isMasterRequest()->willReturn(true);
        $event->getRequest()->willReturn(new Request());

        $middleware->onKernelRequest($event->reveal());

        $exceptionEvent = $this->prophesize(GetResponseForExceptionEvent::class);
        $exceptionEvent->getException()->shouldBeCalled()->willReturn(new Exception());
        $middleware->onKernelException($exceptionEvent->reveal());
    }

    public function testNoSpanIsTaggedOnKernelTerminateIfItIsNotStarted()
    {
        $tracing = TracingBuilder::create()->build();
        $logger = new NullLogger();

        $middleware = new Middleware($tracing, $logger);

        $event = $this->prophesize(GetResponseEvent::class);
        $event->isMasterRequest()->willReturn(false);
        $event->getRequest()->willReturn(new Request());

        $middleware->onKernelRequest($event->reveal());

        $responseEvent = $this->prophesize(PostResponseEvent::class);
        $responseEvent->getRequest()->shouldNotBeCalled();
        $middleware->onKernelTerminate($responseEvent->reveal());
    }

    public function testSpanIsTaggedOnKernelTerminate()
    {
        $tracing = TracingBuilder::create()->build();
        $logger = new NullLogger();

        $middleware = new Middleware($tracing, $logger);

        $request = new Request();
        $event = $this->prophesize(GetResponseEvent::class);
        $event->isMasterRequest()->willReturn(true);
        $event->getRequest()->willReturn($request);

        $middleware->onKernelRequest($event->reveal());

        $exceptionEvent = $this->prophesize(PostResponseEvent::class);
        $exceptionEvent->getRequest()->shouldBeCalled()->willReturn($request);
        $exceptionEvent->getResponse()->shouldBeCalled()->willReturn(new Response);
        $middleware->onKernelTerminate($exceptionEvent->reveal());

        $this->assertNull($tracing->getTracer()->getCurrentSpan());
    }
}
