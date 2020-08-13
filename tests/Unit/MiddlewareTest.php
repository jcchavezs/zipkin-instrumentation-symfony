<?php

namespace ZipkinBundle\Tests\Unit;

use Zipkin\TracingBuilder;
use Zipkin\Samplers\BinarySampler;
use Zipkin\Reporters\InMemory as InMemoryReporter;
use ZipkinBundle\Middleware;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Psr\Log\NullLogger;
use PHPUnit\Framework\TestCase;
use Exception;

final class MiddlewareTest extends TestCase
{
    const HTTP_HOST = 'localhost';
    const HTTP_METHOD = 'OPTIONS';
    const HTTP_PATH = '/foo';
    const TAG_KEY = 'key';
    const TAG_VALUE = 'value';
    const EXCEPTION_MESSAGE = 'message';

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

    private function mockKernel()
    {
        return $this->prophesize(HttpKernelInterface::class)->reveal();
    }

    public function testNoSpanIsTaggedOnKernelExceptionIfItIsNotStarted()
    {
        $reporter = new InMemoryReporter();
        $tracing = TracingBuilder::create()
            ->havingSampler(BinarySampler::createAsAlwaysSample())
            ->havingReporter($reporter)
            ->build();
        $logger = new NullLogger();

        $middleware = new Middleware($tracing, $logger);

        $event = $this->prophesize(KernelEvent::class);
        $event->isMasterRequest()->willReturn(false);
        $event->getRequest()->willReturn(new Request());

        $middleware->onKernelRequest($event->reveal());

        if (class_exists('Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent')) {
            $exceptionEvent = new \Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent(
                $this->mockKernel(),
                new Request(),
                HttpKernelInterface::SUB_REQUEST, // isMasterRequest will be false
                new Exception()
            );
        } else {
            $exceptionEvent = new \Symfony\Component\HttpKernel\Event\ExceptionEvent(
                $this->mockKernel(),
                new Request(),
                HttpKernelInterface::SUB_REQUEST, // isMasterRequest will be false
                new Exception()
            );
        }

        $middleware->onKernelException($exceptionEvent);

        $tracing->getTracer()->flush();
        $spans = $reporter->flush();
        $this->assertCount(0, $spans);
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
            $exceptionEvent = new \Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent(
                $this->mockKernel(),
                new Request(),
                HttpKernelInterface::MASTER_REQUEST, // isMasterRequest will be true
                new Exception(self::EXCEPTION_MESSAGE)
            );
        } else {
            $exceptionEvent = new \Symfony\Component\HttpKernel\Event\ExceptionEvent(
                $this->mockKernel(),
                new Request(),
                HttpKernelInterface::MASTER_REQUEST, // isMasterRequest will be true
                new Exception(self::EXCEPTION_MESSAGE)
            );
        }

        $middleware->onKernelException($exceptionEvent);

        $tracing->getTracer()->flush();
        $spans = $reporter->flush();
        $this->assertCount(1, $spans);
        $this->assertEquals(self::EXCEPTION_MESSAGE, $spans[0]->getError()->getMessage());
    }

    public function testNoSpanIsTaggedOnKernelTerminateIfItIsNotStarted()
    {
        $reporter = new InMemoryReporter();
        $tracing = TracingBuilder::create()
            ->havingSampler(BinarySampler::createAsAlwaysSample())
            ->havingReporter($reporter)
            ->build();
        $logger = new NullLogger();

        $middleware = new Middleware($tracing, $logger);

        $event = $this->prophesize(KernelEvent::class);
        $event->isMasterRequest()->willReturn(false);
        $event->getRequest()->willReturn(new Request());

        $middleware->onKernelRequest($event->reveal());

        if (class_exists('Symfony\Component\HttpKernel\Event\PostResponseEvent')) {
            $terminateEvent = new \Symfony\Component\HttpKernel\Event\PostResponseEvent(
                $this->mockKernel(),
                new Request(),
                new Response()
            );
        } else {
            $terminateEvent = new \Symfony\Component\HttpKernel\Event\TerminateEvent(
                $this->mockKernel(),
                new Request(),
                new Response()
            );
        }

        $middleware->onKernelTerminate($terminateEvent);
        $spans = $reporter->flush();
        $this->assertCount(0, $spans);
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
    public function testSpanIsTaggedOnKernelResponse($responseStatusCode)
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
            $responseEvent = new \Symfony\Component\HttpKernel\Event\FilterResponseEvent(
                $this->mockKernel(),
                $request,
                KernelInterface::MASTER_REQUEST,
                new Response('', $responseStatusCode)
            );
        } else {
            $responseEvent = new \Symfony\Component\HttpKernel\Event\ResponseEvent(
                $this->mockKernel(),
                $request,
                KernelInterface::MASTER_REQUEST,
                new Response('', $responseStatusCode)
            );
        }

        $middleware->onKernelResponse($responseEvent);

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

    public function testSpanScopeIsClosedOnResponse()
    {
        $tracing = TracingBuilder::create()
            ->havingSampler(BinarySampler::createAsAlwaysSample())
            ->build();
        $logger = new NullLogger();

        $middleware = new Middleware($tracing, $logger);

        $request = new Request();

        $event = $this->prophesize(KernelEvent::class);
        $event->isMasterRequest()->willReturn(true);
        $event->getRequest()->willReturn($request);

        $middleware->onKernelRequest($event->reveal());

        if (class_exists('Symfony\Component\HttpKernel\Event\PostResponseEvent')) {
            $responseEvent = new \Symfony\Component\HttpKernel\Event\FilterResponseEvent(
                $this->mockKernel(),
                $request,
                KernelInterface::MASTER_REQUEST,
                new Response()
            );
        } else {
            $responseEvent = new \Symfony\Component\HttpKernel\Event\ResponseEvent(
                $this->mockKernel(),
                $request,
                KernelInterface::MASTER_REQUEST,
                new Response()
            );
        }

        $this->assertNotNull($tracing->getTracer()->getCurrentSpan());

        $middleware->onKernelResponse($responseEvent);

        $this->assertNull($tracing->getTracer()->getCurrentSpan());
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
            $responseEvent = new \Symfony\Component\HttpKernel\Event\PostResponseEvent(
                $this->mockKernel(),
                $request,
                new Response('', $responseStatusCode)
            );
        } else {
            $responseEvent = new \Symfony\Component\HttpKernel\Event\TerminateEvent(
                $this->mockKernel(),
                $request,
                new Response('', $responseStatusCode)
            );
        }

        $middleware->onKernelTerminate($responseEvent);

        $assertTags = [
            'http.host' => self::HTTP_HOST,
            'http.method' => self::HTTP_METHOD,
            'http.path' => self::HTTP_PATH,
            'http.status_code' => (string) $responseStatusCode,
        ];

        if ($responseStatusCode > 399) {
            $assertTags['error'] = (string) $responseStatusCode;
        }

        // There is no need to to `Tracer::flush` here as `onKernelTerminate` does
        // it already.
        $spans = $reporter->flush();
        $this->assertCount(1, $spans);
        $this->assertArraySubset(['tags' => $assertTags], $spans[0]->toArray());
    }

    public function testSpanScopeIsClosedOnTerminate()
    {
        $tracing = TracingBuilder::create()
            ->havingSampler(BinarySampler::createAsAlwaysSample())
            ->build();
        $logger = new NullLogger();

        $middleware = new Middleware($tracing, $logger);

        $request = new Request();

        $event = $this->prophesize(KernelEvent::class);
        $event->isMasterRequest()->willReturn(true);
        $event->getRequest()->willReturn($request);

        $middleware->onKernelRequest($event->reveal());

        if (class_exists('Symfony\Component\HttpKernel\Event\PostResponseEvent')) {
            $responseEvent = new \Symfony\Component\HttpKernel\Event\PostResponseEvent(
                $this->mockKernel(),
                $request,
                new Response()
            );
        } else {
            $responseEvent = new \Symfony\Component\HttpKernel\Event\TerminateEvent(
                $this->mockKernel(),
                $request,
                new Response()
            );
        }

        $this->assertNotNull($tracing->getTracer()->getCurrentSpan());

        $middleware->onKernelTerminate($responseEvent);

        $this->assertNull($tracing->getTracer()->getCurrentSpan());
    }
}
