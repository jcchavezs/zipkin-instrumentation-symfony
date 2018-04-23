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
use Zipkin\Reporters\InMemory;
use Zipkin\Samplers\BinarySampler;
use Zipkin\TracingBuilder;
use ZipkinBundle\Middleware;
use Zipkin\Reporters\InMemory as InMemoryReporter;

class MiddlewareTest extends PHPUnit_Framework_TestCase
{
    const HTTP_HOST = 'localhost';
    const HTTP_METHOD = 'OPTIONS';
    const HTTP_PATH = '/foo';
    const TAG_KEY = 'key';
    const TAG_VALUE = 'value';
    
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
        $reporter = new InMemoryReporter();
        $tracing = TracingBuilder::create()
            ->havingSampler(BinarySampler::createAsAlwaysSample())
            ->havingReporter($reporter)
            ->build();
        $logger = new NullLogger();

        $middleware = new Middleware($tracing, $logger, [self::TAG_KEY => self::TAG_VALUE]);

        $request = new Request([], [], [], [], [], [
            'REQUEST_METHOD' => self::HTTP_METHOD,
            'REQUEST_URI' => self::HTTP_PATH,
            'HTTP_HOST' => self::HTTP_HOST,
        ]);

        $event = $this->prophesize(GetResponseEvent::class);
        $event->isMasterRequest()->willReturn(true);
        $event->getRequest()->willReturn($request);

        $middleware->onKernelRequest($event->reveal());

        $tracing->getTracer()->flush();
        $spans = $reporter->flush();
        $this->assertCount(1, $spans);
        $this->assertArraySubset([
            'tags' => [
                'http.host' => self::HTTP_HOST,
                'http.method' => self::HTTP_METHOD,
                'http.path' => self::HTTP_PATH,
                self::TAG_KEY => self::TAG_VALUE,
            ]
        ], $spans[0]->toArray());
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
        $exceptionEvent->isMasterRequest()->willReturn(false);
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
        $exceptionEvent->isMasterRequest()->willReturn(true);
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
        $responseEvent->isMasterRequest()->willReturn(false);
        $responseEvent->getRequest()->shouldNotBeCalled();
        $middleware->onKernelTerminate($responseEvent->reveal());
    }

    public function testSpanIsTaggedOnKernelTerminate()
    {
        $reporter = new InMemoryReporter();
        $tracing = TracingBuilder::create()
            ->havingSampler(BinarySampler::createAsAlwaysSample())
            ->havingReporter($reporter)
            ->build();
        $logger = new NullLogger();

        $middleware = new Middleware($tracing, $logger);

        $request = new Request([], [], [], [], [], [
            'REQUEST_METHOD' => self::HTTP_METHOD,
            'REQUEST_URI' => self::HTTP_PATH,
            'HTTP_HOST' => self::HTTP_HOST,
        ]);

        $event = $this->prophesize(GetResponseEvent::class);
        $event->isMasterRequest()->willReturn(true);
        $event->getRequest()->willReturn($request);

        $middleware->onKernelRequest($event->reveal());

        $exceptionEvent = $this->prophesize(PostResponseEvent::class);
        $exceptionEvent->isMasterRequest()->willReturn(true);
        $exceptionEvent->getRequest()->shouldBeCalled()->willReturn($request);
        $exceptionEvent->getResponse()->shouldBeCalled()->willReturn(new Response);
        $middleware->onKernelTerminate($exceptionEvent->reveal());

        $tracing->getTracer()->flush();
        $spans = $reporter->flush();
        $this->assertCount(1, $spans);
        $this->assertArraySubset([
            'tags' => [
                'http.host' => self::HTTP_HOST,
                'http.method' => self::HTTP_METHOD,
                'http.path' => self::HTTP_PATH
            ]
        ], $spans[0]->toArray());
    }
}
