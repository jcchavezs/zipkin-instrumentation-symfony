<?php


namespace ZipkinBundle\Components\Messenger;

use Symfony\Component\Messenger\Envelope;
use Zipkin\Propagation\B3;
use Zipkin\Propagation\Getter;
use Zipkin\Kind;
use Zipkin\Tracing;

class ZipkinReceiveHandler implements ZipkinHandlerInterface
{
    /**
     * @var \Zipkin\Tracer
     */
    private $tracer;
    /**
     * @var B3
     */
    private $b3;

    public function __construct(Tracing $tracing, B3 $b3)
    {
        $this->tracer = $tracing->getTracer();
        $this->b3 = $b3;
    }

    public function handle(Envelope $envelope): Envelope
    {
        /** @var Getter|B3Stamp $stamp */
        $stamp = $envelope->last(B3Stamp::class);
        if (null !== $stamp) {
            $carrier = [];
            $span = $this->tracer->nextSpan(($this->b3->getExtractor($stamp))($carrier));
        } else {
            $span = $this->tracer->nextSpan();
        }

        $span->start();

        $span->setKind(Kind\CONSUMER);

        return $envelope;
    }
}
