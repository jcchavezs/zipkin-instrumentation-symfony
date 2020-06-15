<?php


namespace ZipkinBundle\Components\Messenger;

use Symfony\Component\Messenger\Envelope;
use Zipkin\Propagation\Map;
use Zipkin\Kind;
use Zipkin\Tracing;

class ZipkinReceiveHandler implements ZipkinHandlerInterface
{
    /**
     * @var \Zipkin\Tracer
     */
    private $tracer;
    /**
     * @var callable
     */
    private $extractor;

    public function __construct(Tracing $tracing)
    {
        $this->tracer = $tracing->getTracer();
        $this->extractor = $tracing->getPropagation()->getExtractor(new Map());
    }

    public function handle(Envelope $envelope): Envelope
    {
        $stamp = $envelope->last(ZipkinStamp::class);
        if (null !== $stamp) {
            $span = $this->tracer->nextSpan(($this->extractor)($stamp->getContext()));
        } else {
            $span = $this->tracer->nextSpan();
        }

        $span->start();

        $span->setKind(Kind\CONSUMER);

        return $envelope;
    }
}
