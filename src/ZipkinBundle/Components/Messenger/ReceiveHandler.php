<?php


namespace ZipkinBundle\Components\Messenger;

use Zipkin\Tracing;
use Zipkin\Kind;
use ZipkinBundle\Components\Messenger\PropagationStampAccessor;
use Throwable;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Envelope;

class ReceiveHandler
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
        $this->extractor = $tracing->getPropagation()->getExtractor(new PropagationStampAccessor());
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $stamp = $envelope->last(PropagationStamp::class);
        if (null === $stamp) {
            $span = $this->tracer->nextSpan();
        } else {
            $extracted = ($this->extractor)($stamp);
            $span = $this->tracer->nextSpan($extracted);
        }

        $span->start();
        $span->setName('msg.received');
        $span->setKind(Kind\CONSUMER);
        $scopeCloser = $this->tracer->openScope($span);

        try {
            return $stack->next()->handle($envelope, $stack);
        } catch (Throwable $e) {
            $span->setError($e);
            throw $e;
        } finally {
            $span->finish();
            $scopeCloser();
        }
    }
}
