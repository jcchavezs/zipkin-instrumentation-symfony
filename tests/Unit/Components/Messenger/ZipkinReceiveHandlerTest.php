<?php

namespace ZipkinBundle\Tests\Unit\Components\Messenger;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Zipkin\Reporters\InMemory as InMemoryReporter;
use Zipkin\Samplers\BinarySampler;
use Zipkin\Tracing;
use Zipkin\TracingBuilder;
use ZipkinBundle\Components\Messenger\ZipkinReceiveHandler;
use PHPUnit\Framework\TestCase;
use ZipkinBundle\Components\Messenger\ZipkinStamp;

class ZipkinReceiveHandlerTest extends TestCase
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

    public function testGenerateSpanIfNotAlreadyInitialized()
    {
        $message = new \stdClass();
        $envelope = new Envelope($message, [new ReceivedStamp('default.bus')]);

        $sut = new ZipkinReceiveHandler($this->tracing);

        $sut->handle($envelope);

        $this->tracing->getTracer()->flush();
        $spans = $this->reporter->flush();

        $this->assertCount(1, $spans);
    }
}
