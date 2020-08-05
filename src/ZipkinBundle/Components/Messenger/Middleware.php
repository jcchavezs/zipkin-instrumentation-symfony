<?php

namespace ZipkinBundle\Components\Messenger;

use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Envelope;

final class Middleware implements MiddlewareInterface
{
    /**
     * @var ZipkinSendHandler
     */
    private $sendHandler;

    /**
     * @var ZipkinReceiveHandler
     */
    private $receiveHandler;

    public function __construct(ReceiveHandler $receiveHandler, SendHandler $sendHandler)
    {
        $this->receiveHandler = $receiveHandler;
        $this->sendHandler = $sendHandler;
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if (null !== $envelope->last(ReceivedStamp::class)) {
            // Message just has been received...
            return $this->receiveHandler->handle($envelope, $stack);
        }

        // Message was just originally dispatched
        return $this->sendHandler->handle($envelope, $stack);
    }
}
