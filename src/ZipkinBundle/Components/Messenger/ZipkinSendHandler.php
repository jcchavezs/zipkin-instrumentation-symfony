<?php


namespace ZipkinBundle\Components\Messenger;

use Symfony\Component\Messenger\Envelope;

use Zipkin\Kind;
use Zipkin\Propagation\B3;
use Zipkin\Tags;
use Zipkin\Tracing;

class ZipkinSendHandler implements ZipkinHandlerInterface
{
    /**
     * @var \Zipkin\Tracer
     */
    private $tracer;

    public function __construct(Tracing $tracing)
    {
        $this->tracer = $tracing->getTracer();
    }

    public function handle(Envelope $envelope): Envelope
    {
        if ($envelope->all(ZipkinStamp::class)) {
            return $envelope;
        }

        $span = $this->tracer->nextSpan();
        $span->start();

        $span->setKind(Kind\PRODUCER);
        $span->tag(Tags\LOCAL_COMPONENT, 'symfony');
        $span->setName(get_class($envelope->getMessage()));

        $stamp = new ZipkinStamp;
        $stamp->add(B3::SPAN_ID_NAME, $span->getContext()->getSpanId());
        $stamp->add(B3::PARENT_SPAN_ID_NAME, $span->getContext()->getParentId());
        $stamp->add(B3::TRACE_ID_NAME, $span->getContext()->getTraceId());

        return $envelope->with($stamp);
    }
}
