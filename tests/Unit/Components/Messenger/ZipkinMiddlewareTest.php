<?php

namespace ZipkinBundle\Tests\Unit\Components\Messenger;

use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use ZipkinBundle\Components\Messenger\ZipkinMiddleware;
use PHPUnit\Framework\TestCase;
use ZipkinBundle\Components\Messenger\ZipkinReceiveHandler;
use ZipkinBundle\Components\Messenger\ZipkinSendHandler;

class ZipkinMiddlewareTest extends TestCase
{
    public function testSendHandlerCall()
    {
        $message = new \stdClass();
        $envelope = new Envelope($message);

        /** @var ObjectProphecy|ZipkinSendHandler $sendHandler */
        $sendHandler = $this->prophesize(ZipkinSendHandler::class);
        $sendHandler->handle($envelope)->shouldBeCalledOnce()->willReturn($envelope);
        
        /** @var ObjectProphecy|ZipkinReceiveHandler $receiveHandler */
        $receiveHandler = $this->prophesize(ZipkinReceiveHandler::class);
        $receiveHandler->handle($envelope)->shouldBeCalledTimes(0);

        $stack = $this->prophesize(StackInterface::class);
        $next = $this->prophesize(MiddlewareInterface::class);
        $next->handle($envelope, Argument::type(StackInterface::class))->shouldBeCalledOnce()->willReturn($envelope);
        $stack->next()->shouldBeCalledOnce()->willReturn($next->reveal());

        $sut = new ZipkinMiddleware($sendHandler->reveal(), $receiveHandler->reveal());

        $sut->handle($envelope, $stack->reveal());
    }

    public function testReceiveHandlerCall()
    {
        $message = new \stdClass();
        $envelope = new Envelope($message, [new ReceivedStamp('default_bus')]);

        /** @var ObjectProphecy|ZipkinSendHandler $sendHandler */
        $sendHandler = $this->prophesize(ZipkinSendHandler::class);
        $sendHandler->handle($envelope)->shouldBeCalledTimes(0);
        /** @var ObjectProphecy|ZipkinReceiveHandler $receiveHandler */
        $receiveHandler = $this->prophesize(ZipkinReceiveHandler::class);
        $receiveHandler->handle($envelope)->shouldBeCalledOnce()->willReturn($envelope);

        $stack = $this->prophesize(StackInterface::class);
        $next = $this->prophesize(MiddlewareInterface::class);
        $next->handle($envelope, Argument::type(StackInterface::class))->shouldBeCalledOnce()->willReturn($envelope);
        $stack->next()->shouldBeCalledOnce()->willReturn($next->reveal());

        $sut = new ZipkinMiddleware($sendHandler->reveal(), $receiveHandler->reveal());

        $sut->handle($envelope, $stack->reveal());
    }
}
