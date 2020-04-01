<?php

namespace ZipkinBundle\Tests\Unit;

use Exception;
use DG\BypassFinals;
use Psr\Log\NullLogger;
use Zipkin\TracingBuilder;
use ZipkinBundle\Middleware;
use PHPUnit\Framework\TestCase;
use Zipkin\Samplers\BinarySampler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Zipkin\Reporters\InMemory as InMemoryReporter;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\HttpKernel\Event\PostResponseEvent;

class MiddlewareTest extends TestCase
{
    const HTTP_HOST = 'localhost';
    const HTTP_METHOD = 'OPTIONS';
    const HTTP_PATH = '/foo';
    const TAG_KEY = 'key';
    const TAG_VALUE = 'value';
    const EXCEPTION_MESSAGE = 'message';

    public function setUp()
    {
        BypassFinals::enable();
    }

    public function testSpanIsNotCreatedOnNonMasterRequest()
    {
        $tracing = TracingBuilder::create()->build();
        $logger = new NullLogger();

        $middleware = new Middleware($tracing, $logger);

        $event = $this->prophesize(KernelEvent::class);
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

        $event = $this->prophesize(KernelEvent::class);
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

        $event = $this->prophesize(KernelEvent::class);
        $event->isMasterRequest()->willReturn(false);
        $event->getRequest()->willReturn(new Request());

        $middleware->onKernelRequest($event->reveal());

        if (class_exists('Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent')) {
            $exceptionEvent = $this->prophesize(\Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent::class);
            $exceptionEvent->getException()->shouldNotBeCalled();
        } else {
            $exceptionEvent = $this->prophesize(\Symfony\Component\HttpKernel\Event\ExceptionEvent::class);
            $exceptionEvent->getThrowable()->shouldNotBeCalled();
        }

        $exceptionEvent->isMasterRequest()->willReturn(false);
        $middleware->onKernelException($exceptionEvent->reveal());
    }

    public function testSpanIsTaggedOnKernelException()
    {
        $reporter = new InMemoryReporter();
        $tracing = TracingBuilder::create()
            ->havingSampler(BinarySampler::createAsAlwaysSample())
            ->havingReporter($reporter)
            ->build();
        $logger = new NullLogger();
        $middleware = new Middleware($tracing, $logger);

        $event = $this->prophesize(KernelEvent::class);
        $event->isMasterRequest()->willReturn(true);
        $event->getRequest()->willReturn(new Request());

        $middleware->onKernelRequest($event->reveal());

        if (class_exists('Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent')) {
            $exceptionEvent = $this->prophesize(\Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent::class);
            $exceptionEvent->getException()->shouldBeCalled()->willReturn(new Exception(self::EXCEPTION_MESSAGE));
        } else {
            $exceptionEvent = $this->prophesize(\Symfony\Component\HttpKernel\Event\ExceptionEvent::class);
            $exceptionEvent->getThrowable()->shouldBeCalled()->willReturn(new Exception(self::EXCEPTION_MESSAGE));
        }

        $exceptionEvent->isMasterRequest()->willReturn(true);
        $middleware->onKernelException($exceptionEvent->reveal());

        $tracing->getTracer()->flush();
        $spans = $reporter->flush();
        $this->assertCount(1, $spans);
        $this->assertArraySubset([
            'tags' => [
                'error' => self::EXCEPTION_MESSAGE,
            ]
        ], $spans[0]->toArray());
    }

    public function testNoSpanIsTaggedOnKernelTerminateIfItIsNotStarted()
    {
        $tracing = TracingBuilder::create()->build();
        $logger = new NullLogger();

        $middleware = new Middleware($tracing, $logger);

        $event = $this->prophesize(KernelEvent::class);
        $event->isMasterRequest()->willReturn(false);
        $event->getRequest()->willReturn(new Request());

        $middleware->onKernelRequest($event->reveal());

        if (class_exists('Symfony\Component\HttpKernel\Event\PostResponseEvent')) {
            $responseEvent = $this->prophesize(\Symfony\Component\HttpKernel\Event\PostResponseEvent::class);
        } else {
            $responseEvent = $this->prophesize(\Symfony\Component\HttpKernel\Event\TerminateEvent::class);
        }

        $responseEvent->isMasterRequest()->willReturn(false);
        $responseEvent->getRequest()->shouldNotBeCalled();
        $middleware->onKernelTerminate($responseEvent->reveal());
    }

    public function statusCodeProvider()
    {
        return [
            [200],
            [300],
            [400],
            [500]
        ];
    }

    /**
     * @dataProvider statusCodeProvider
     */
    public function testSpanIsTaggedOnKernelTerminate($responseStatusCode)
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

        $event = $this->prophesize(KernelEvent::class);
        $event->isMasterRequest()->willReturn(true);
        $event->getRequest()->willReturn($request);

        $middleware->onKernelRequest($event->reveal());

        if (class_exists('Symfony\Component\HttpKernel\Event\PostResponseEvent')) {
            $responseEvent = $this->prophesize(\Symfony\Component\HttpKernel\Event\PostResponseEvent::class);
        } else {
            $responseEvent = $this->prophesize(\Symfony\Component\HttpKernel\Event\TerminateEvent::class);
        }

        $responseEvent->isMasterRequest()->willReturn(true);
        $responseEvent->getRequest()->shouldBeCalled()->willReturn($request);
        $responseEvent->getResponse()->shouldBeCalled()->willReturn(new Response('', $responseStatusCode));
        $middleware->onKernelTerminate($responseEvent->reveal());

        $assertTags = [
            'http.host' => self::HTTP_HOST,
            'http.method' => self::HTTP_METHOD,
            'http.path' => self::HTTP_PATH,
            'http.status_code' => (string) $responseStatusCode,
        ];

        if ($responseStatusCode > 399) {
            $assertTags['error'] = (string) $responseStatusCode;
        }

        $tracing->getTracer()->flush();
        $spans = $reporter->flush();
        $this->assertCount(1, $spans);
        $this->assertArraySubset(['tags' => $assertTags], $spans[0]->toArray());
    }
}
