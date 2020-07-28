<?php

namespace ZipkinBundle\Tests\Unit\Components\Messenger;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Zipkin\Propagation\B3;
use Zipkin\Reporters\InMemory as InMemoryReporter;
use Zipkin\Samplers\BinarySampler;
use Zipkin\Tracing;
use Zipkin\TracingBuilder;
use ZipkinBundle\Components\Messenger\ZipkinReceiveHandler;
use PHPUnit\Framework\TestCase;
use ZipkinBundle\Components\Messenger\B3Stamp;

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
        $this->b3 = new B3();
    }

    public function testGenerateSpanIfNotAlreadyInitialized()
    {
        $message = new \stdClass();
        $envelope = new Envelope($message, [new ReceivedStamp('default.bus')]);

        $sut = new ZipkinReceiveHandler($this->tracing, $this->b3);

        $sut->handle($envelope);

        $this->tracing->getTracer()->flush();
        $spans = $this->reporter->flush();

        $this->assertCount(1, $spans);
    }
}
