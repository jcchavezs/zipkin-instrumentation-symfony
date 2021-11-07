<?php

namespace ZipkinBundle\Tests\Unit;

use Zipkin\TracingBuilder;
use Zipkin\Samplers\BinarySampler;
use Zipkin\Reporters\InMemory as InMemoryReporter;
use Zipkin\Recording\ReadbackSpan;
use ZipkinBundle\Middleware;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Psr\Log\NullLogger;
use PHPUnit\Framework\TestCase;
use Exception;

/**
 * @deprecated
 */
final class MiddlewareTest extends TestCase
{
    private const HTTP_METHOD = 'OPTIONS';
    private const HTTP_PATH = '/foo';
    private const TAG_KEY = 'key';
    private const TAG_VALUE = 'value';
    private const EXCEPTION_MESSAGE = 'message';

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
        ]);

        $event = $this->prophesize(KernelEvent::class);
        $event->isMasterRequest()->willReturn(true);
        $event->getRequest()->willReturn($request);

        $middleware->onKernelRequest($event->reveal());

        $tracing->getTracer()->flush();
        $spans = $reporter->flush();
        $this->assertCount(1, $spans);
        $tags = $spans[0]->getTags();
        $this->assertEquals(self::HTTP_METHOD, $tags['http.method']);
        $this->assertEquals(self::HTTP_PATH, $tags['http.path']);
        $this->assertEquals(self::TAG_VALUE, $tags[self::TAG_KEY]);
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

        $exceptionEvent = new ExceptionEvent(
            $this->mockKernel(),
            new Request(),
            HttpKernelInterface::SUB_REQUEST, // isMasterRequest will be false
            new Exception()
        );

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

        $exceptionEvent = new ExceptionEvent(
            $this->mockKernel(),
            new Request(),
            HttpKernelInterface::MASTER_REQUEST, // isMasterRequest will be true
            new Exception(self::EXCEPTION_MESSAGE)
        );

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

        $terminateEvent = new TerminateEvent(
            $this->mockKernel(),
            new Request(),
            new Response()
        );

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
        ]);

        $event = $this->prophesize(KernelEvent::class);
        $event->isMasterRequest()->willReturn(true);
        $event->getRequest()->willReturn($request);

        $middleware->onKernelRequest($event->reveal());

        $responseEvent = new ResponseEvent(
            $this->mockKernel(),
            $request,
            KernelInterface::MASTER_REQUEST,
            new Response('', $responseStatusCode)
        );

        $middleware->onKernelResponse($responseEvent);

        $assertTags = [
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

        /**
         * @var ReadbackSpan $span
         */
        $span = $spans[0];
        $this->assertEquals($assertTags, $span->getTags());
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

        $responseEvent = new ResponseEvent(
            $this->mockKernel(),
            $request,
            KernelInterface::MASTER_REQUEST,
            new Response()
        );

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
        ]);

        $event = $this->prophesize(KernelEvent::class);
        $event->isMasterRequest()->willReturn(true);
        $event->getRequest()->willReturn($request);

        $middleware->onKernelRequest($event->reveal());

        $responseEvent = new TerminateEvent(
            $this->mockKernel(),
            $request,
            new Response('', $responseStatusCode)
        );

        $middleware->onKernelTerminate($responseEvent);

        $assertTags = [
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

        /**
         * @var ReadbackSpan $span
         */
        $span = $spans[0];
        $this->assertEquals($assertTags, $span->getTags());
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

        $responseEvent = new TerminateEvent(
            $this->mockKernel(),
            $request,
            new Response()
        );

        $this->assertNotNull($tracing->getTracer()->getCurrentSpan());

        $middleware->onKernelTerminate($responseEvent);

        $this->assertNull($tracing->getTracer()->getCurrentSpan());
    }
}
