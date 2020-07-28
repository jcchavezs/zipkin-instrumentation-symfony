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
use ZipkinBundle\Components\Messenger\B3Stamp;

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
    /**
     * @var B3
     */
    private $b3;

    protected function setUp()
    {
        $this->reporter = new InMemoryReporter();
        $this->tracing = TracingBuilder::create()
            ->havingSampler(BinarySampler::createAsAlwaysSample())
            ->havingReporter($this->reporter)
            ->build();
        $this->b3 = new B3;
    }

    public function testSkipHandlerIfStampAlreadyExists()
    {
        $message = new \stdClass();
        $envelope = new Envelope($message, [new B3Stamp()]);

        $sut = new ZipkinSendHandler($this->tracing, $this->b3);

        $sut->handle($envelope);

        $this->assertNull($this->tracing->getTracer()->getCurrentSpan());
    }
    public function testGenerateStampIfNotAlreadyInitialized()
    {
        $message = new \stdClass();
        $envelope = new Envelope($message);

        $sut = new ZipkinSendHandler($this->tracing, $this->b3);

        $sut->handle($envelope);

        $this->tracing->getTracer()->flush();
        $spans = $this->reporter->flush();

        $this->assertCount(1, $spans);
    }

    public function testAddZipkinStampToEnvelope()
    {
        $message = new \stdClass();
        $envelope = new Envelope($message);
        $carrier = [];

        $sut = new ZipkinSendHandler($this->tracing, $this->b3);

        $newEnvelope = $sut->handle($envelope);

        $this->tracing->getTracer()->flush();
        $spans = $this->reporter->flush();
        $denormalizedSpan = $spans[0]->toArray();

        /** @var []B3Stamp $stamps */
        $stamps = $newEnvelope->all(B3Stamp::class);

        $this->assertCount(1, $spans);
        $this->assertCount(1, $stamps);

        $singleValueHeader = sprintf("%s-%s-%d", $denormalizedSpan['traceId'], $denormalizedSpan['id'], 1);

        $this->assertEquals($singleValueHeader, $stamps[0]->get($carrier, 'b3'));
    }
}
