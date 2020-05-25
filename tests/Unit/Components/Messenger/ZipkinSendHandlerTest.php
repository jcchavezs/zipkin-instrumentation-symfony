<?php

namespace ZipkinBundle\Tests\Unit\Components\Messenger;

use Symfony\Component\Messenger\Envelope;
use Zipkin\Reporters\InMemory as InMemoryReporter;
use Zipkin\Samplers\BinarySampler;
use Zipkin\Propagation\B3;
use Zipkin\Tracing;
use Zipkin\TracingBuilder;
use ZipkinBundle\Components\Messenger\ZipkinSendHandler;
use PHPUnit\Framework\TestCase;
use ZipkinBundle\Components\Messenger\ZipkinStamp;

class ZipkinSendHandlerTest extends TestCase
{
    /**
     * @var \Zipkin\DefaultTracing|Tracing
     */
    private $tracing;
    /**
     * @var InMemoryReporter
     */
    private $reporter;

    protected function setUp()
    {
        $this->reporter = new InMemoryReporter();
        $this->tracing = TracingBuilder::create()
            ->havingSampler(BinarySampler::createAsAlwaysSample())
            ->havingReporter($this->reporter)
            ->build();
    }

    public function testSkipHandlerIfStampAlreadyExists()
    {
        $message = new \stdClass();
        $envelope = new Envelope($message, [new ZipkinStamp()]);

        $sut = new ZipkinSendHandler($this->tracing);

        $sut->handle($envelope);

        $this->assertNull($this->tracing->getTracer()->getCurrentSpan());
    }
    public function testGenerateStampIfNotAlreadyInitialized()
    {
        $message = new \stdClass();
        $envelope = new Envelope($message);

        $sut = new ZipkinSendHandler($this->tracing);

        $sut->handle($envelope);

        $this->tracing->getTracer()->flush();
        $spans = $this->reporter->flush();

        $this->assertCount(1, $spans);
    }

    public function testAddZipkinStampToEnvelope()
    {
        $message = new \stdClass();
        $envelope = new Envelope($message);

        $sut = new ZipkinSendHandler($this->tracing);

        $newEnvelope = $sut->handle($envelope);

        $this->tracing->getTracer()->flush();
        $spans = $this->reporter->flush();
        $denormalizedSpan = $spans[0]->toArray();

        /** @var []ZipkinStamp $stamps */
        $stamps = $newEnvelope->all(ZipkinStamp::class);

        $this->assertCount(1, $spans);
        $this->assertCount(1, $stamps);

        $this->assertArraySubset([
            B3::PARENT_SPAN_ID_NAME => $denormalizedSpan['parentId'],
            B3::TRACE_ID_NAME => $denormalizedSpan['traceId'],
            B3::SPAN_ID_NAME => $denormalizedSpan['id']
        ],
        $stamps[0]->getContext());
    }
}
