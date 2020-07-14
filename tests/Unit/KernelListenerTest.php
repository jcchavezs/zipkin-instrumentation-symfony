<?php

namespace ZipkinBundle\Tests\Unit;

use Zipkin\TracingBuilder;
use Zipkin\Samplers\BinarySampler;
use Zipkin\Reporters\InMemory as InMemoryReporter;
use ZipkinBundle\KernelListener;
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

final class KernelListenerTest extends TestCase
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

        $kernelListener = new KernelListener($tracing, $logger);

        $event = $this->prophesize(KernelEvent::class);
        $event->isMasterRequest()->willReturn(false);

        $kernelListener->onKernelRequest($event->reveal());

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

        $kernelListener = new KernelListener($tracing, $logger, [self::TAG_KEY => self::TAG_VALUE]);

        $request = new Request([], [], [], [], [], [
            'REQUEST_METHOD' => self::HTTP_METHOD,
            'REQUEST_URI' => self::HTTP_PATH,
            'HTTP_HOST' => self::HTTP_HOST,
        ]);

        $event = $this->prophesize(KernelEvent::class);
        $event->isMasterRequest()->willReturn(true);
        $event->getRequest()->willReturn($request);

        $kernelListener->onKernelRequest($event->reveal());

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

        $kernelListener = new KernelListener($tracing, $logger);

        $event = $this->prophesize(KernelEvent::class);
        $event->isMasterRequest()->willReturn(false);
        $event->getRequest()->willReturn(new Request());

        $kernelListener->onKernelRequest($event->reveal());

        $exceptionEvent = new ExceptionEvent(
            $this->mockKernel(),
            new Request(),
            HttpKernelInterface::SUB_REQUEST, // isMasterRequest will be false
            new Exception()
        );

        $kernelListener->onKernelException($exceptionEvent);

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
        $kernelListener = new KernelListener($tracing, $logger);

        $event = $this->prophesize(KernelEvent::class);
        $event->isMasterRequest()->willReturn(true);
        $event->getRequest()->willReturn(new Request());

        $kernelListener->onKernelRequest($event->reveal());

        $exceptionEvent = new ExceptionEvent(
            $this->mockKernel(),
            new Request(),
            HttpKernelInterface::MASTER_REQUEST, // isMasterRequest will be true
            new Exception(self::EXCEPTION_MESSAGE)
        );

        $kernelListener->onKernelException($exceptionEvent);

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
        $reporter = new InMemoryReporter();
        $tracing = TracingBuilder::create()
            ->havingSampler(BinarySampler::createAsAlwaysSample())
            ->havingReporter($reporter)
            ->build();
        $logger = new NullLogger();

        $kernelListener = new KernelListener($tracing, $logger);

        $event = $this->prophesize(KernelEvent::class);
        $event->isMasterRequest()->willReturn(false);
        $event->getRequest()->willReturn(new Request());

        $kernelListener->onKernelRequest($event->reveal());

        $terminateEvent = new TerminateEvent(
            $this->mockKernel(),
            new Request(),
            new Response()
        );

        $kernelListener->onKernelTerminate($terminateEvent);
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

        $kernelListener = new KernelListener($tracing, $logger);

        $request = new Request([], [], [], [], [], [
            'REQUEST_METHOD' => self::HTTP_METHOD,
            'REQUEST_URI' => self::HTTP_PATH,
            'HTTP_HOST' => self::HTTP_HOST,
        ]);

        $event = $this->prophesize(KernelEvent::class);
        $event->isMasterRequest()->willReturn(true);
        $event->getRequest()->willReturn($request);

        $kernelListener->onKernelRequest($event->reveal());

        $responseEvent = new ResponseEvent(
            $this->mockKernel(),
            $request,
            KernelInterface::MASTER_REQUEST,
            new Response('', $responseStatusCode)
        );

        $kernelListener->onKernelResponse($responseEvent);

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

        $kernelListener = new KernelListener($tracing, $logger);

        $request = new Request();

        $event = $this->prophesize(KernelEvent::class);
        $event->isMasterRequest()->willReturn(true);
        $event->getRequest()->willReturn($request);

        $kernelListener->onKernelRequest($event->reveal());

        $responseEvent = new ResponseEvent(
            $this->mockKernel(),
            $request,
            KernelInterface::MASTER_REQUEST,
            new Response()
        );

        $this->assertNotNull($tracing->getTracer()->getCurrentSpan());

        $kernelListener->onKernelResponse($responseEvent);

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

        $kernelListener = new KernelListener($tracing, $logger);

        $request = new Request([], [], [], [], [], [
            'REQUEST_METHOD' => self::HTTP_METHOD,
            'REQUEST_URI' => self::HTTP_PATH,
            'HTTP_HOST' => self::HTTP_HOST,
        ]);

        $event = $this->prophesize(KernelEvent::class);
        $event->isMasterRequest()->willReturn(true);
        $event->getRequest()->willReturn($request);

        $kernelListener->onKernelRequest($event->reveal());

        $responseEvent = new TerminateEvent(
            $this->mockKernel(),
            $request,
            new Response('', $responseStatusCode)
        );

        $kernelListener->onKernelTerminate($responseEvent);

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

        $kernelListener = new KernelListener($tracing, $logger);

        $request = new Request();

        $event = $this->prophesize(KernelEvent::class);
        $event->isMasterRequest()->willReturn(true);
        $event->getRequest()->willReturn($request);

        $kernelListener->onKernelRequest($event->reveal());

        $responseEvent = new TerminateEvent(
            $this->mockKernel(),
            $request,
            new Response()
        );

        $this->assertNotNull($tracing->getTracer()->getCurrentSpan());

        $kernelListener->onKernelTerminate($responseEvent);

        $this->assertNull($tracing->getTracer()->getCurrentSpan());
    }
}
