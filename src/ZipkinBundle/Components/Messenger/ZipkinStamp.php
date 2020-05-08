<?php

namespace ZipkinBundle\Components\Messenger;

use Symfony\Component\Messenger\Stamp\StampInterface;

class ZipkinStamp implements StampInterface
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
}