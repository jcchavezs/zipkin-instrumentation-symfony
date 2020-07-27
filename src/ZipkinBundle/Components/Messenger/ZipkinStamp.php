<?php

namespace ZipkinBundle\Components\Messenger;

use Symfony\Component\Messenger\Stamp\StampInterface;
use Zipkin\Propagation\RemoteSetter;

class ZipkinStamp implements StampInterface, RemoteSetter
{
    public $context = [];

    public function getContext(): array
    {
        return $this->context;
    }

    public function add(string $key, ?string $value): void
    {
        $this->context[$key] = $value;
    }

    public function getKind(): string
    {
        // TODO: Implement getKind() method.
    }

    public function put(&$carrier, string $key, string $value): void
    {
        // TODO: Implement put() method.
    }
}
