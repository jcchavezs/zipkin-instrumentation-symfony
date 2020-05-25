<?php

namespace ZipkinBundle\Tests\Unit\Components\Messenger;

use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use ZipkinBundle\Components\Messenger\ZipkinHandlerInterface;
use ZipkinBundle\Components\Messenger\ZipkinMiddleware;
use PHPUnit\Framework\TestCase;

class ZipkinMiddlewareTest extends TestCase
{
    public function testSendHandlerCall()
    {
        $message = new \stdClass();
        $envelope = new Envelope($message);

        /** @var ObjectProphecy|ZipkinHandlerInterface $sendHandler */
        $sendHandler = $this->prophesize(ZipkinHandlerInterface::class);
        $sendHandler->handle($envelope)->shouldBeCalledOnce();
        /** @var ObjectProphecy|ZipkinHandlerInterface $receiveHandler */
        $receiveHandler = $this->prophesize(ZipkinHandlerInterface::class);
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

        /** @var ObjectProphecy|ZipkinHandlerInterface $sendHandler */
        $sendHandler = $this->prophesize(ZipkinHandlerInterface::class);
        $sendHandler->handle($envelope)->shouldBeCalledTimes(0);
        /** @var ObjectProphecy|ZipkinHandlerInterface $receiveHandler */
        $receiveHandler = $this->prophesize(ZipkinHandlerInterface::class);
        $receiveHandler->handle($envelope)->shouldBeCalledOnce();

        $stack = $this->prophesize(StackInterface::class);
        $next = $this->prophesize(MiddlewareInterface::class);
        $next->handle($envelope, Argument::type(StackInterface::class))->shouldBeCalledOnce()->willReturn($envelope);
        $stack->next()->shouldBeCalledOnce()->willReturn($next->reveal());

        $sut = new ZipkinMiddleware($sendHandler->reveal(), $receiveHandler->reveal());

        $sut->handle($envelope, $stack->reveal());
    }
}
