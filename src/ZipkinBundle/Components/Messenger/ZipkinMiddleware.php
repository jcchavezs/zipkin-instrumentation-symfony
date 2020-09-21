<?php

namespace ZipkinBundle\Components\Messenger;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

class ZipkinMiddleware implements MiddlewareInterface
{
    /**
     * @var ZipkinSendHandler
     */
    private $sendHandler;
    /**
     * @var ZipkinReceiveHandler
     */
    private $receiveHandler;

    public function __construct(ZipkinSendHandler $sendHandler, ZipkinReceiveHandler $receiveHandler)
    {
        $this->sendHandler = $sendHandler;
        $this->receiveHandler = $receiveHandler;
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if ($envelope->all(ReceivedStamp::class)) {
            $envelope = $this->receiveHandler->handle($envelope);
        } else {
            $envelope = $this->sendHandler->handle($envelope);
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
