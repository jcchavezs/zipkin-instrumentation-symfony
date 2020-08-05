<?php


namespace ZipkinBundle\Components\Messenger;

use Zipkin\Tracing;

use Zipkin\Tags;
use Zipkin\Kind;
use Throwable;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Envelope;

class SendHandler
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
        $this->injector = $tracing->getPropagation()->getInjector(new PropagationStampAccessor());
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if ($envelope->all(B3Stamp::class)) {
            // there is an stamp already hence propagation is done.
            return $envelope;
        }

        $span = $this->tracer->nextSpan();
        $span->start();
        $span->setName('message.sent');
        $span->setKind(Kind\PRODUCER);
        $span->tag(Tags\LOCAL_COMPONENT, 'symfony');
        $scopeCloser = $this->tracer->openScope($span);

        $stamp = new PropagationStamp();
        ($this->injector)($span->getContext(), $stamp);

        try {
            $envelope = $stack->next()->handle($envelope->with($stamp), $stack);
            return $envelope;
        } catch (Throwable $e) {
            $span->setError($e);
            throw $e;
        } finally {
            $span->finish();
            $scopeCloser();
        }
    }
}
