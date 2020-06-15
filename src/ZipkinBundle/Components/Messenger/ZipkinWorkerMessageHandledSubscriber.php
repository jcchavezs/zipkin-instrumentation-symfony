<?php


namespace ZipkinBundle\Components\Messenger;


use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Zipkin\Tracing;

class ZipkinWorkerMessageHandledSubscriber implements EventSubscriberInterface
{
    /**
     * @var \Zipkin\Tracer
     */
    private $tracer;

    public function __construct(Tracing $tracing)
    {
        $this->tracer = $tracing->getTracer();
    }

    public static function getSubscribedEvents()
    {
        return [
            WorkerMessageHandledEvent::class => 'onWorkerMessageHandledEvent',
        ];
    }

    public function onWorkerMessageHandledEvent(WorkerMessageHandledEvent $event)
    {
        $span = $this->tracer->getCurrentSpan();
        if ($span === null) {
            return;
        }

        $span->finish();
        $this->flushTracer();
    }

    private function flushTracer()
    {
        try {
            $this->tracer->flush();
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }
    }
}