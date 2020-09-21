<?php

namespace App\Controller;

use App\Message\TestMessage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;

class HealthController
{
    /**
     * @var MessageBusInterface
     */
    private $bus;

    public function __construct(MessageBusInterface $bus)
    {
        $this->bus = $bus;
    }

    /**
    * @Route("/_health")
    */
    public function health()
    {
        return new Response();
    }

    /**
     * @Route("/_send_message")
     */
    public function sendMessage()
    {
        $message = new TestMessage;
        $message->id = "I love ID";
        $message->message = "This is a fancy Message";

        $this->bus->dispatch($message);

        return new Response();
    }
}
