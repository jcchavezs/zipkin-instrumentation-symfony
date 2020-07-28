<?php


namespace ZipkinBundle\Components\Messenger;

use Symfony\Component\Messenger\Envelope;
use Zipkin\Propagation\B3;
use Zipkin\Propagation\Getter;
use Zipkin\Kind;
use Zipkin\Tracing;

class ZipkinReceiveHandler
{
    /**
     * @var \Zipkin\Tracer
     */
    private $tracer;

    public function __construct(Tracing $tracing)
    {
        $this->tracer = $tracing->getTracer();
        $this->extractor = $tracing->getPropagation()->getExtractor(new PropagationStamp());
    }

    public function handle(Envelope $envelope): Envelope
    {
        /** @var Getter|B3Stamp $stamp */
        $stamp = $envelope->last(B3Stamp::class);
        if (null !== $stamp) {
            $span = $this->tracer->nextSpan(($this->extractor)($stamp));
        } else {
            $span = $this->tracer->nextSpan();
        }

        $span->start();

        $span->setKind(Kind\CONSUMER);

        return $envelope;
    }
}
