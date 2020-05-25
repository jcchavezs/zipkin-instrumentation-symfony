<?php


namespace ZipkinBundle\Components\Messenger;


use Symfony\Component\Messenger\Envelope;
use Zipkin\Tracing;

interface ZipkinHandlerInterface
{
    public function __construct(Tracing $tracing);
    public function handle(Envelope $envelope): Envelope;
}