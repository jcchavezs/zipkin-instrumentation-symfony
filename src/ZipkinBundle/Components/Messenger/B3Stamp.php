<?php

namespace ZipkinBundle\Components\Messenger;

use Symfony\Component\Messenger\Stamp\StampInterface;
use Zipkin\Propagation\RemoteSetter;
use Zipkin\Propagation\Getter;
use Zipkin\Kind;

class B3Stamp implements StampInterface, RemoteSetter, Getter
{
    private $context = [];

    public function getKind(): string
    {
        return Kind\PRODUCER;
    }

    public function put(&$carrier, string $key, string $value): void
    {
        $this->context[$key] = $value;
    }

    public function get($carrier, string $key): ?string
    {
        return $this->context[$key] ?? null;
    }
}
