<?php


namespace ZipkinBundle\Components\Messenger;

use Symfony\Component\Messenger\Envelope;

use Zipkin\Kind;
use Zipkin\Propagation\B3;
use Zipkin\Tags;
use Zipkin\Tracing;

class ZipkinSendHandler
{
    /**
     * @var \Zipkin\Tracer
     */
    private $tracer;
    /**
     * @var callable
     */
    private $injector;

    public function __construct(Tracing $tracing)
    {
        $this->tracer = $tracing->getTracer();
        $this->injector = $tracing->getPropagation()->getInjector(new PropagationStamp());
    }

    public function handle(Envelope $envelope): Envelope
    {
        if ($envelope->all(B3Stamp::class)) {
            return $envelope;
        }

        $span = $this->tracer->nextSpan();
        $span->start();

        $span->setKind(Kind\PRODUCER);
        $span->tag(Tags\LOCAL_COMPONENT, 'symfony');
        $span->setName('SEND_MESSAGE_'.(new \ReflectionClass($envelope->getMessage()))->getShortName());

        $stamp = new B3Stamp;
        ($this->injector)($span->getContext(), $stamp);

        return $envelope->with($stamp);
    }
}
